<?php

namespace Tests\Feature;

use App\Exports\AdminExport;
use App\Models\Beverage;
use App\Models\BeverageSale;
use App\Models\Expense;
use App\Models\MembershipTransaction;
use App\Models\Shift;
use App\Models\User;
use Database\Seeders\ShiftSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UserShiftAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_seed_can_be_repeated_without_replacing_assigned_master_ids(): void
    {
        $ids = Shift::query()->orderBy('id')->pluck('id')->all();
        $user = $this->cashier();
        $this->seed(ShiftSeeder::class);

        $this->assertSame($ids, Shift::query()->orderBy('id')->pluck('id')->all());
        $this->assertCount(6, $ids);
        $this->assertSame('Pagi', $user->refresh()->assignedShift->name);
        $this->seed(ShiftSeeder::class);
        $this->assertSame($ids, Shift::query()->orderBy('id')->pluck('id')->all());
        foreach (['admin', 'kasir_gym', 'kasir_minum'] as $role) {
            foreach (['Pagi' => ['07:00:00', '15:00:00'], 'Siang' => ['14:00:00', '22:00:00']] as $name => $hours) {
                $this->assertDatabaseHas('shifts', ['code' => $name === 'Pagi' ? 'P' : 'S', 'role' => $role, 'name' => $name, 'start_time' => $hours[0], 'end_time' => $hours[1]]);
            }
        }
    }

    public function test_account_creation_validates_role_and_stores_shift_id(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $component = Livewire::actingAs($admin)->test('pages::dashboard.admin.akun.admin.create')
            ->set('name', 'Kasir Baru')->set('age', 25)->set('phone', '081234567891')
            ->set('alamat', 'Alamat kasir')->set('email', 'kasir-baru@example.com')->set('password', 'secret123');
        $this->assertSame(['kasir_gym'], $component->get('shifts')->pluck('role')->unique()->values()->all());
        $component->set('shift', $this->shift('admin', 'Pagi')->id)->call('store')->assertHasErrors('shift');
        $component->set('shift', $this->shift('kasir_gym', 'Pagi')->id)->call('store')->assertHasNoErrors();
        $this->assertSame($this->shift('kasir_gym', 'Pagi')->id, User::where('email', 'kasir-baru@example.com')->firstOrFail()->shift);
    }

    public function test_shift_seeder_creates_short_codes_from_empty_master(): void
    {
        Shift::query()->delete();
        $this->seed(ShiftSeeder::class);
        $this->assertDatabaseCount('shifts', 6);
        $this->assertSame(['P', 'S'], Shift::query()->distinct()->orderBy('code')->pluck('code')->all());
    }

    public function test_shift_seeder_rolls_back_code_changes_when_codes_are_ambiguous(): void
    {
        Shift::factory()->create(['role' => 'kasir_minum', 'code' => 'S', 'name' => 'Siang']);
        $before = Shift::query()->orderBy('id')->pluck('code', 'id')->all();
        try {
            $this->seed(ShiftSeeder::class);
            $this->fail('Ambiguous codes should stop seeding.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('ambigu', $exception->getMessage());
        }
        $this->assertSame($before, Shift::query()->orderBy('id')->pluck('code', 'id')->all());
    }

    public function test_account_role_changes_reset_shift_and_edit_rejects_mismatched_role(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $cashier = $this->cashier();
        $component = Livewire::actingAs($admin)->test('pages::dashboard.admin.akun.admin.edit', ['user' => $cashier])
            ->assertSet('shift', $cashier->shift)
            ->set('role', 'kasir_minum')->assertSet('shift', null);
        $this->assertSame(['kasir_minum'], $component->get('shifts')->pluck('role')->unique()->values()->all());
        $component->set('shift', $cashier->shift)->call('update')->assertHasErrors('shift');
        $component->set('shift', $this->shift('kasir_minum', 'Siang')->id)->call('update')->assertHasNoErrors();
        $this->assertSame($this->shift('kasir_minum', 'Siang')->id, $cashier->refresh()->shift);

        Livewire::actingAs($admin)->test('pages::dashboard.admin.akun.admin.create')
            ->set('shift', $this->shift('kasir_gym', 'Pagi')->id)
            ->set('role', 'kasir_minum')->assertSet('shift', null);
    }

    public function test_admin_account_edit_preserves_role_and_assigned_shift(): void
    {
        $admin = $this->cashier('admin', 'Siang');

        Livewire::actingAs($admin)->test('pages::dashboard.admin.akun.admin.edit', ['user' => $admin])
            ->assertSet('role', 'admin')
            ->assertSeeHtml('<option value="admin" wire:key="role-option-admin">Manager</option>')
            ->assertSet('shift', $admin->shift)
            ->set('name', 'Manager Diperbarui')
            ->call('update')->assertHasNoErrors()
            ->assertRedirect(route('admin.akun.admin.index'));

        $this->assertSame('admin', $admin->refresh()->role);
        $this->assertSame('Manager Diperbarui', $admin->name);
        $this->assertSame($this->shift('admin', 'Siang')->id, $admin->shift);
    }

    public function test_admin_account_edit_rejects_invalid_role_and_other_role_shift(): void
    {
        $admin = $this->cashier('admin');
        $component = Livewire::actingAs($admin)->test('pages::dashboard.admin.akun.admin.edit', ['user' => $admin]);
        $this->assertSame(['admin'], $component->get('shifts')->pluck('role')->unique()->values()->all());
        $component->set('shift', $this->shift('kasir_gym', 'Pagi')->id)
            ->call('update')->assertHasErrors('shift');
        $component->set('role', 'invalid_role')->call('update')->assertHasErrors('role');
        $this->assertSame('admin', $admin->refresh()->role);
        $this->assertSame($this->shift('admin', 'Pagi')->id, $admin->shift);
    }

    public function test_edit_cannot_promote_staff_to_admin_by_manipulating_role(): void
    {
        $admin = $this->cashier('admin');
        $cashier = $this->cashier();
        Livewire::actingAs($admin)->test('pages::dashboard.admin.akun.admin.edit', ['user' => $cashier])
            ->assertDontSeeHtml('<option value="admin" wire:key="role-option-admin">Manager</option>')
            ->set('role', 'admin')->call('update')->assertHasErrors('role');
        $this->assertSame('kasir_gym', $cashier->refresh()->role);
    }

    public function test_navbar_filters_roles_and_saves_id_instead_of_name(): void
    {
        $cashier = $this->cashier();
        $component = Livewire::actingAs($cashier)->test('dashboard.navbar')->call('openShiftModal')
            ->assertSet('selectedShift', (string) $cashier->shift)->assertSee('Pagi')->assertSee('07:00');
        $this->assertSame(['kasir_gym'], $component->get('shifts')->pluck('role')->unique()->values()->all());
        $component->set('selectedShift', (string) $this->shift('admin', 'Siang')->id)->call('saveShift')->assertHasErrors('selectedShift');
        $component->set('selectedShift', (string) $this->shift('kasir_gym', 'Siang')->id)->call('saveShift')->assertHasNoErrors();
        $this->assertSame($this->shift('kasir_gym', 'Siang')->id, $cashier->refresh()->shift);
    }

    public function test_expenses_keep_snapshot_after_user_assignment_changes_and_filter_by_snapshot(): void
    {
        $cashier = $this->cashier();
        Livewire::actingAs($cashier)->test('pages::dashboard.admin.pengeluaran.create')
            ->set('description', 'Pengeluaran pagi historis')->set('amount', 10000)->call('save')->assertHasNoErrors();
        $expense = Expense::query()->firstOrFail();
        $this->assertSame('Pagi', $expense->shift);
        $cashier->update(['shift' => $this->shift('kasir_gym', 'Siang')->id]);
        $this->assertSame('Pagi', $expense->refresh()->shift);
        $admin = User::factory()->create(['role' => 'admin']);
        Livewire::actingAs($admin)->test('pages::dashboard.admin.pengeluaran.index')
            ->set('shift', 'pagi')->assertSee('Pengeluaran pagi historis')
            ->set('shift', 'siang')->assertDontSee('Pengeluaran pagi historis');
    }

    public function test_custom_snapshot_name_is_saved_when_expense_is_created(): void
    {
        $cashier = $this->cashier();
        $cashier->update(['shift' => Shift::factory()->create(['role' => 'kasir_gym', 'name' => 'Sore'])->id]);
        Livewire::actingAs($cashier)->test('pages::dashboard.admin.pengeluaran.create')
            ->set('description', 'Pengeluaran sore')->set('amount', 10000)->call('save')->assertHasNoErrors();
        $this->assertDatabaseHas('expenses', ['description' => 'Pengeluaran sore', 'shift' => 'Sore']);
        $this->assertSame('Sore', $cashier->refresh()->shiftSnapshot());
    }

    public function test_beverage_api_uses_authenticated_shift_and_ignores_submitted_snapshot(): void
    {
        $cashier = $this->cashier('kasir_minum', 'Siang');
        $beverage = Beverage::factory()->create(['harga_jual' => 5000]);
        $this->actingAs($cashier)->post(route('admin.beverages.pos.process'), [
            'selected_products' => json_encode([['beverage_id' => $beverage->id, 'nama_produk' => $beverage->nama_produk, 'harga_satuan' => 5000, 'jumlah_beli' => 1]]),
            'nama_staff' => $cashier->name,
            'shift' => 'pagi',
            'keterangan_bayar' => 'cash',
            'cash_received' => '5000',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('beverage_sales', ['beverage_id' => $beverage->id, 'shift' => 'siang']);
    }

    public function test_null_assignment_keeps_nullable_snapshot(): void
    {
        $user = User::factory()->create(['shift' => null]);
        $this->assertNull($user->assignedShift);
        $this->assertNull($user->shiftSnapshot());
    }

    public function test_custom_beverage_shift_creates_sale_and_reduces_stock(): void
    {
        $cashier = $this->cashier('kasir_minum');
        $cashier->update(['shift' => Shift::factory()->create(['name' => 'Sore', 'role' => 'kasir_minum'])->id]);
        $beverage = Beverage::factory()->create(['stok_sekarang' => 10]);

        $this->actingAs($cashier)->post(route('admin.beverages.pos.process'), [
            'selected_products' => json_encode([['beverage_id' => $beverage->id, 'nama_produk' => $beverage->nama_produk, 'harga_satuan' => 5000, 'jumlah_beli' => 1]]),
            'nama_staff' => $cashier->name,
            'shift' => 'pagi',
            'keterangan_bayar' => 'cash',
            'cash_received' => '5000',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('beverage_sales', ['beverage_id' => $beverage->id, 'shift' => 'Sore']);
        $this->assertSame(9, (int) $beverage->refresh()->stok_sekarang);
    }

    public function test_beverage_debt_repayment_preserves_original_shift_snapshot(): void
    {
        $cashier = $this->cashier('kasir_minum', 'Siang');
        $beverage = Beverage::factory()->create();
        $sale = BeverageSale::create([
            'beverage_id' => $beverage->id,
            'nama_produk' => $beverage->nama_produk,
            'nama_staff' => $cashier->name,
            'waktu_transaksi' => now(),
            'shift' => 'pagi',
            'jumlah_beli' => 1,
            'harga_satuan' => 5000,
            'total_harga' => 5000,
            'keterangan_bayar' => 'hutang',
            'nama_penghutang' => 'Pelanggan Lama',
            'is_lunas' => false,
        ]);

        Livewire::actingAs($cashier)->test('pages::dashboard.admin.beverages.hutang')
            ->call('openConfirmModal', $sale->id)->call('confirmLunas')->assertHasNoErrors();

        $this->assertDatabaseHas('beverage_sales', [
            'parent_beverage_sale_id' => $sale->id,
            'shift' => 'pagi',
            'keterangan_bayar' => 'deposit_hutang_cash',
            'is_lunas' => true,
        ]);
        $this->assertTrue((bool) $sale->refresh()->is_lunas);
    }

    public function test_other_income_uses_selected_admin_snapshot_and_preserves_it_after_reassignment(): void
    {
        $loggedIn = $this->cashier('kasir_gym', 'Siang');
        $selectedAdmin = $this->cashier('kasir_gym', 'Pagi');
        $payer = User::factory()->create(['role' => 'member']);

        Livewire::actingAs($loggedIn)->test('pages::dashboard.admin.penjualan.index')
            ->set('selectedUserId', $payer->id)->set('adminId', $selectedAdmin->id)
            ->set('incomeCategory', 'Merchandise')->set('incomeAmount', 50000)
            ->set('incomePaymentMethod', 'cash')->call('saveIncome')->assertHasNoErrors();

        $transaction = MembershipTransaction::query()->sole();
        $this->assertSame($selectedAdmin->id, $transaction->admin_id);
        $this->assertSame('Pagi', $transaction->shift);
        $selectedAdmin->update(['shift' => $this->shift('kasir_gym', 'Siang')->id]);
        $this->assertSame('Pagi', $transaction->refresh()->shift);
    }

    public function test_account_export_displays_shift_name_instead_of_foreign_key(): void
    {
        $cashier = $this->cashier();
        $export = new AdminExport($cashier->email);
        $this->assertSame('Pagi', $export->map($export->query()->sole())[4]);
    }

    private function shift(string $role, string $name): Shift
    {
        return Shift::query()->forRole($role)->where('name', $name)->firstOrFail();
    }

    private function cashier(string $role = 'kasir_gym', string $name = 'Pagi'): User
    {
        return User::factory()->create(['role' => $role, 'shift' => $this->shift($role, $name)->id, 'address' => 'Alamat kasir', 'age' => 25, 'gender' => 'Laki-laki', 'phone' => fake()->unique()->numerify('08##########')]);
    }
}
