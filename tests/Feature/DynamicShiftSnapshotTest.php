<?php

namespace Tests\Feature;

use App\Models\Beverage;
use App\Models\BeverageSale;
use App\Models\Expense;
use App\Models\MembershipTransaction;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DynamicShiftSnapshotTest extends TestCase
{
    use RefreshDatabase;

    public function test_middle_snapshots_survive_master_rename_across_all_transaction_types(): void
    {
        $shift = Shift::factory()->create(['role' => 'kasir_gym', 'name' => 'Middle']);
        $cashier = User::factory()->create(['role' => 'kasir_gym', 'shift' => $shift->id]);
        $payer = User::factory()->create(['role' => 'member']);

        Livewire::actingAs($cashier)->test('pages::dashboard.admin.pengeluaran.create')
            ->set('description', 'Pengeluaran Middle')->set('amount', 10000)
            ->call('save')->assertHasNoErrors();

        Livewire::actingAs($cashier)->test('pages::dashboard.admin.penjualan.index')
            ->set('selectedUserId', $payer->id)->set('adminId', $cashier->id)
            ->set('incomeCategory', 'Merchandise')->set('incomeAmount', 50000)
            ->set('incomePaymentMethod', 'cash')->call('saveIncome')->assertHasNoErrors();

        $drinkShift = Shift::factory()->create(['role' => 'kasir_minum', 'name' => 'Middle']);
        $drinkCashier = User::factory()->create(['role' => 'kasir_minum', 'shift' => $drinkShift->id]);
        $beverage = Beverage::factory()->create(['harga_jual' => 5000]);
        $this->actingAs($drinkCashier)->post(route('admin.beverages.pos.process'), [
            'selected_products' => json_encode([['beverage_id' => $beverage->id, 'nama_produk' => $beverage->nama_produk, 'harga_satuan' => 5000, 'jumlah_beli' => 1]]),
            'nama_staff' => $drinkCashier->name,
            'shift' => 'pagi',
            'keterangan_bayar' => 'cash',
            'cash_received' => '5000',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $shift->update(['name' => 'Evening']);
        $drinkShift->update(['name' => 'Evening']);

        $this->assertSame($shift->id, $cashier->refresh()->shift);
        $this->assertSame('Middle', Expense::query()->sole()->shift);
        $this->assertSame('Middle', MembershipTransaction::query()->sole()->shift);
        $this->assertSame('Middle', BeverageSale::query()->sole()->shift);
        $options = Shift::filterOptions([MembershipTransaction::class, Expense::class, BeverageSale::class]);
        $this->assertSame('Middle', $options->get('middle'));
        $this->assertSame('Evening', $options->get('evening'));
    }

    public function test_dynamic_filters_include_historical_shifts_and_match_income_and_expense_totals(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $payer = User::factory()->create(['role' => 'member']);
        Shift::factory()->create(['name' => 'Upcoming']);
        foreach (['Middle' => [50000, 10000], 'Pagi' => [90000, 30000]] as $name => [$income, $expense]) {
            MembershipTransaction::create([
                'invoice_number' => 'DYNAMIC-'.$name,
                'user_id' => $payer->id, 'admin_id' => $admin->id, 'shift' => $name,
                'transaction_type' => 'new', 'package_name' => 'Income '.$name,
                'amount' => $income, 'payment_method' => 'cash', 'payment_date' => today(),
            ]);
            Expense::create([
                'admin_id' => $admin->id, 'shift' => $name,
                'description' => 'Expense '.$name, 'amount' => $expense, 'expense_date' => today(),
            ]);
        }
        Expense::create(['admin_id' => $admin->id, 'shift' => 'Expense Only', 'description' => 'Historical expense', 'amount' => 1000, 'expense_date' => today()]);

        $sales = Livewire::actingAs($admin)->test('pages::dashboard.admin.penjualan.index')
            ->set('shift', 'middle');
        $this->assertSame(['Middle'], $sales->get('transactions')->pluck('shift')->all());
        $summary = $sales->get('summary');
        $this->assertEquals(50000, $summary['cash']);
        $this->assertEquals(10000, $summary['pengeluaran']);
        $this->assertEquals(40000, $summary['real_cash']);
        $this->assertTrue($sales->get('shiftOptions')->has('expense only'));
        $this->assertTrue($sales->get('shiftOptions')->has('upcoming'));
        $sales->set('shift', '');
        $this->assertCount(2, $sales->get('transactions'));

        $expenses = Livewire::actingAs($admin)->test('pages::dashboard.admin.pengeluaran.index')
            ->set('shift', 'middle');
        $this->assertSame(['Middle'], $expenses->get('expenses')->pluck('shift')->all());
        $this->assertTrue($expenses->get('shiftOptions')->has('expense only'));
        $expenses->set('shift', '');
        $this->assertCount(3, $expenses->get('expenses'));
    }

    public function test_beverage_filters_keep_historical_names_and_deduplicate_legacy_case(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $beverage = Beverage::factory()->create();
        Shift::factory()->create(['name' => 'Upcoming']);
        foreach (['Middle', 'pagi'] as $name) {
            BeverageSale::create([
                'beverage_id' => $beverage->id, 'nama_produk' => $beverage->nama_produk,
                'nama_staff' => $admin->name, 'waktu_transaksi' => now(), 'shift' => $name,
                'jumlah_beli' => 1, 'harga_satuan' => 5000, 'total_harga' => 5000,
                'keterangan_bayar' => 'cash',
            ]);
        }
        $component = Livewire::actingAs($admin)->test('pages::dashboard.admin.beverages.sales')
            ->set('shift', 'middle');
        $this->assertSame(['Middle'], $component->get('sales')->pluck('shift')->all());
        $options = $component->get('shiftOptions');
        $this->assertSame('Middle', $options->get('middle'));
        $this->assertTrue($options->has('upcoming'));
        $this->assertCount(1, $options->filter(fn (string $name): bool => strtolower($name) === 'pagi'));
        $component->set('shift', '');
        $this->assertCount(2, $component->get('sales'));
    }
}
