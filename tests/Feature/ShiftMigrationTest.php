<?php

namespace Tests\Feature;

use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ShiftMigrationTest extends TestCase
{
    public function test_dynamic_snapshot_migration_preserves_legacy_values_and_blocks_lossy_rollback(): void
    {
        $migration = require database_path('migrations/2026_09_10_124544_allow_dynamic_transaction_shift_snapshots.php');
        $migration->down();
        $admin = User::factory()->create(['role' => 'admin']);
        foreach (['Pagi', 'Siang', null] as $shift) {
            DB::table('expenses')->insert([
                'admin_id' => $admin->id, 'shift' => $shift, 'description' => 'Legacy',
                'amount' => 1000, 'expense_date' => today(),
            ]);
        }
        $before = DB::table('expenses')->orderBy('id')->get()->toJson();
        $migration->up();
        $this->assertSame($before, DB::table('expenses')->orderBy('id')->get()->toJson());
        DB::table('expenses')->where('shift', 'Pagi')->update(['shift' => 'Middle']);
        try {
            $migration->down();
            $this->fail('Rollback must reject custom snapshots.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Rollback dibatalkan', $exception->getMessage());
        }
        $this->assertDatabaseHas('expenses', ['shift' => 'Middle']);
        $this->assertSame('varchar', Schema::getColumnType('expenses', 'shift'));
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('jim_test', DB::connection()->getDatabaseName());
        $this->artisan('migrate:fresh', ['--force' => true])->assertExitCode(0);
    }

    protected function tearDown(): void
    {
        RefreshDatabaseState::$migrated = false;
        parent::tearDown();
    }

    public function test_migration_preserves_assignments_and_nulls_and_rejects_incomplete_mapping(): void
    {
        $prepare = require database_path('migrations/2026_09_10_115034_seed_legacy_role_shifts.php');
        $string = require database_path('migrations/2026_09_10_115035_make_users_shift_string.php');
        $map = require database_path('migrations/2026_09_10_115036_map_users_shift_to_master_ids.php');
        $foreign = require database_path('migrations/2026_09_10_115037_make_users_shift_foreign_key.php');

        $foreign->down();
        $map->down();
        $string->down();
        Shift::query()->delete();
        Shift::factory()->create(['id' => 100, 'role' => 'pt']);

        $expected = [];
        foreach (['admin', 'kasir_gym', 'kasir_minum'] as $role) {
            foreach (['Pagi', 'Siang'] as $name) {
                $user = User::factory()->create(['role' => $role]);
                DB::table('users')->where('id', $user->id)->update(['shift' => $name]);
                $expected[$user->id] = ['role' => $role, 'name' => $name];
            }
        }
        $unassigned = User::factory()->create(['role' => 'pt']);
        DB::table('users')->where('id', $unassigned->id)->update(['shift' => 'Pagi']);

        try {
            $prepare->up();
            $this->fail('Unsupported role mapping should fail before changes.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Mapping', $exception->getMessage());
            $this->assertDatabaseCount('shifts', 1);
        }
        DB::table('users')->where('id', $unassigned->id)->update(['shift' => null]);
        $prepare->up();
        $string->up();

        $duplicate = Shift::factory()->create(['code' => 'KSG-PAGI', 'role' => 'kasir_gym', 'name' => 'Pagi']);
        try {
            $map->up();
            $this->fail('Ambiguous mapping should not update any user.');
        } catch (\RuntimeException $exception) {
            foreach ($expected as $id => $assignment) {
                $this->assertSame($assignment['name'], DB::table('users')->where('id', $id)->value('shift'));
            }
        }
        $duplicate->delete();
        $map->up();
        $foreign->up();

        foreach ($expected as $id => $assignment) {
            $user = User::findOrFail($id);
            $this->assertGreaterThan(100, $user->shift);
            $this->assertSame($assignment['name'], $user->assignedShift->name);
            $this->assertSame($assignment['role'], $user->assignedShift->role);
        }
        $this->assertNull($unassigned->fresh()->shift);
        $this->assertFalse(Schema::hasColumn('users', 'shift_id'));

        $foreign->down();
        $map->down();
        $string->down();
        foreach ($expected as $id => $assignment) {
            $this->assertSame($assignment['name'], DB::table('users')->where('id', $id)->value('shift'));
        }

        $string->up();
        $map->up();
        $foreign->up();
    }
}
