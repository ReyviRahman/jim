<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackfillHikvisionEmployeeNumberMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_prefixes_empty_employee_numbers_with_two_zeroes(): void
    {
        $nullMappingUser = User::factory()->create([
            'id' => 1,
            'hikvision_employee_no' => null,
        ]);
        $blankMappingUser = User::factory()->create([
            'id' => 10,
            'hikvision_employee_no' => '',
        ]);
        $customMappingUser = User::factory()->create([
            'id' => 20,
            'hikvision_employee_no' => 'CUSTOM-20',
        ]);
        $ownIdMappingUser = User::factory()->create([
            'id' => 30,
            'hikvision_employee_no' => '30',
        ]);

        $this->migration()->up();

        $this->assertSame('001', $nullMappingUser->refresh()->hikvision_employee_no);
        $this->assertSame('0010', $blankMappingUser->refresh()->hikvision_employee_no);
        $this->assertSame('CUSTOM-20', $customMappingUser->refresh()->hikvision_employee_no);
        $this->assertSame('30', $ownIdMappingUser->refresh()->hikvision_employee_no);
    }

    public function test_backfill_is_idempotent(): void
    {
        $user = User::factory()->create([
            'id' => 40,
            'hikvision_employee_no' => null,
        ]);

        $migration = $this->migration();

        $migration->up();
        $migration->up();

        $this->assertSame('0040', $user->refresh()->hikvision_employee_no);
    }

    private function migration(): Migration
    {
        return require database_path('migrations/2026_09_02_034603_backfill_hikvision_employee_no_from_user_ids.php');
    }
}
