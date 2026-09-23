<?php

namespace Tests\Feature;

use App\Actions\DeleteBeverageSale;
use App\Actions\SettleBeverageDebt;
use App\Exports\BeverageInvoiceExport;
use App\Models\Beverage;
use App\Models\BeverageInvoice;
use App\Models\BeverageSale;
use App\Models\BeverageStokSnapshot;
use App\Models\DepositBeverage;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class BeverageSaleDeletionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('jim_test', DB::connection()->getDatabaseName());
        $this->travelTo(CarbonImmutable::parse('2026-09-23 12:00:00', 'Asia/Jakarta'));
        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    public function test_both_pages_preview_and_restore_past_stock_including_inactive_products(): void
    {
        foreach (['sales' => 'confirmDelete', 'hutang' => 'deleteHutang'] as $pageName => $open) {
            $product = $this->product();
            $sale = $this->sale($product, 'hutang', '2026-09-17 23:59:59');
            $product->delete();
            $page = Livewire::test('pages::dashboard.admin.beverages.'.$pageName)
                ->call($open, $sale->id)->assertSee('Pengembalian 3 pcs')->assertSee('20 → 23');
            $this->assertSame(10, $page->get('saleSnapshotChanges')->count());
            $page->call('changeSaleImpactPage', 1);
            $this->assertSame(2, $page->get('saleSnapshotChanges')->count());
            $page->call('deleteSale')->assertHasNoErrors()->assertSet('showDeleteModal', false);
            $this->assertModelMissing($sale);
            $this->assertSame(23, $product->refresh()->stok_sekarang);
            $this->assertSame(20, $this->balance($product, '2026-09-16', 'last'));
            $this->assertSame(20, $this->balance($product, '2026-09-17', 'init'));
            $this->assertSame(23, $this->balance($product, '2026-09-17', 'last'));
            $this->assertSame(23, $this->balance($product, '2026-09-23', 'init'));
            $page->call('deleteSale');
            $this->assertNull(app(DeleteBeverageSale::class)->execute($sale->id, 'old'));
            $this->assertSame(23, $product->refresh()->stok_sekarang);
        }
    }

    public function test_today_restores_stock_for_all_stock_methods_without_requiring_last_snapshot(): void
    {
        foreach (['cash', 'tf_bca_qris', 'hutang', 'deposit', 'operasional'] as $method) {
            $product = $this->product();
            $sale = $this->sale($product, $method);
            $this->deleteTransaction($sale);
            $this->assertSame(23, $product->refresh()->stok_sekarang);
            $this->assertSame(20, $this->balance($product, '2026-09-23', 'init'));
            $this->assertDatabaseMissing('beverage_stok_snapshots', ['beverage_id' => $product->id, 'tanggal' => '2026-09-23', 'tipe' => 'last']);
        }
    }

    public function test_non_stock_entries_do_not_require_snapshots_and_repayments_track_remaining_children(): void
    {
        $product = Beverage::factory()->create(['stok_sekarang' => 20]);
        $expense = $this->sale($product, 'pengeluaran_umum');
        $this->deleteTransaction($expense);
        $root = $this->sale($product, 'hutang');
        $root->update(['is_lunas' => true]);
        $first = $this->sale($product, 'deposit_hutang_cash');
        $second = $this->sale($product, 'deposit_hutang_qris');
        foreach ([$first, $second] as $child) {
            $child->update(['parent_beverage_sale_id' => $root->id]);
        }
        $this->deleteTransaction($first);
        $this->assertTrue((bool) $root->refresh()->is_lunas);
        $this->deleteTransaction($second);
        $this->assertFalse((bool) $root->refresh()->is_lunas);
        $this->assertSame(20, $product->refresh()->stok_sekarang);
        $this->assertDatabaseCount('beverage_stok_snapshots', 0);
    }

    public function test_deleting_parent_removes_settlements_and_restores_stock_only_once(): void
    {
        $product = $this->product();
        $sale = $this->sale($product, 'hutang');
        $this->assertTrue(app(SettleBeverageDebt::class)->execute($sale->id, 'deposit_hutang_cash'));
        $this->assertFalse(app(SettleBeverageDebt::class)->execute($sale->id, 'deposit_hutang_qris'));
        $preview = app(DeleteBeverageSale::class)->preview($sale->id);
        $this->assertSame(1, $preview['settlement_count']);
        $this->assertNull(app(DeleteBeverageSale::class)->execute($sale->id, $preview['fingerprint']));
        $this->assertDatabaseCount('beverage_sales', 0);
        $this->assertSame(23, $product->refresh()->stok_sekarang);
        $this->assertFalse(app(SettleBeverageDebt::class)->execute($sale->id, 'deposit_hutang_cash'));
    }

    public function test_missing_snapshots_invalid_quantity_and_missing_product_block_all_changes(): void
    {
        $product = $this->product();
        $sale = $this->sale($product, 'cash', '2026-09-17 12:00:00');
        BeverageStokSnapshot::where('beverage_id', $product->id)->where('tanggal', '2026-09-20')->where('tipe', 'last')->delete();
        $preview = app(DeleteBeverageSale::class)->preview($sale->id);
        $this->assertStringContainsString('snapshot last 2026-09-20', implode(' ', $preview['errors']));
        $this->assertNotNull(app(DeleteBeverageSale::class)->execute($sale->id, $preview['fingerprint']));
        $this->assertModelExists($sale);
        $this->assertSame(20, $product->refresh()->stok_sekarang);
        $sale->update(['jumlah_beli' => 0]);
        $this->assertGreaterThan(0, app(DeleteBeverageSale::class)->preview($sale->id)['error_count']);
        $sale->update(['jumlah_beli' => 3, 'beverage_id' => null]);
        $this->assertStringContainsString('tidak ditemukan', implode(' ', app(DeleteBeverageSale::class)->preview($sale->id)['errors']));
    }

    public function test_stale_preview_must_be_confirmed_again_and_negative_snapshot_blocks_deletion(): void
    {
        $product = $this->product();
        $sale = $this->sale($product, 'cash', '2026-09-17 12:00:00');
        $page = Livewire::test('pages::dashboard.admin.beverages.sales')->call('confirmDelete', $sale->id);
        $product->increment('stok_sekarang', 1);
        $page->call('deleteSale')->assertSee('Dampak terbaru')->assertSet('showDeleteModal', true);
        $this->assertModelExists($sale);
        $page->call('deleteSale')->assertSet('showDeleteModal', false);
        $this->assertSame(24, $product->refresh()->stok_sekarang);
        $sale = $this->sale($product, 'cash', '2026-09-17 12:00:00');
        BeverageStokSnapshot::where('beverage_id', $product->id)->where('tanggal', '2026-09-18')->where('tipe', 'init')->update(['jumlah' => -10]);
        $preview = app(DeleteBeverageSale::class)->preview($sale->id);
        $this->assertStringContainsString('negatif', implode(' ', $preview['errors']));
        $this->assertNotNull(app(DeleteBeverageSale::class)->execute($sale->id, $preview['fingerprint']));
        $this->assertSame(24, $product->refresh()->stok_sekarang);
    }

    public function test_deposit_refund_and_change_removal_are_atomic_and_used_change_blocks_deletion(): void
    {
        $product = $this->product();
        $sale = $this->sale($product, 'cash');
        $used = DepositBeverage::create(['nama_pelanggan' => 'Pelanggan', 'nominal' => 10000, 'sisa_nominal' => 7000, 'is_used' => false]);
        $sale->update(['deposit_beverage_id' => $used->id, 'deposit_amount' => 3000]);
        $change = DepositBeverage::create(['beverage_sale_id' => $sale->id, 'nama_pelanggan' => 'Kembalian', 'nominal' => 2000, 'sisa_nominal' => 2000, 'is_used' => false]);
        $preview = app(DeleteBeverageSale::class)->preview($sale->id);
        $change->update(['sisa_nominal' => 1000]);
        $blocked = app(DeleteBeverageSale::class)->execute($sale->id, $preview['fingerprint']);
        $this->assertGreaterThan(0, $blocked['error_count']);
        $this->assertSame(7000, $used->refresh()->sisa_nominal);
        $this->assertSame(20, $product->refresh()->stok_sekarang);
        $change->update(['sisa_nominal' => 2000]);
        $this->deleteTransaction($sale);
        $this->assertSame(10000, $used->refresh()->sisa_nominal);
        $this->assertModelMissing($change);
        $this->assertSame(23, $product->refresh()->stok_sekarang);
    }

    public function test_new_settlement_invalidates_preview_and_failed_delete_rolls_back_everything(): void
    {
        $product = $this->product();
        $sale = $this->sale($product, 'hutang', '2026-09-17 12:00:00');
        $deposit = DepositBeverage::create(['nama_pelanggan' => 'Deposit', 'nominal' => 10000, 'sisa_nominal' => 7000, 'is_used' => false]);
        $sale->update(['deposit_beverage_id' => $deposit->id, 'deposit_amount' => 3000]);
        $change = DepositBeverage::create(['beverage_sale_id' => $sale->id, 'nama_pelanggan' => 'Kembalian', 'nominal' => 1000, 'sisa_nominal' => 1000, 'is_used' => false]);
        $action = app(DeleteBeverageSale::class);
        $preview = $action->preview($sale->id);
        app(SettleBeverageDebt::class)->execute($sale->id, 'deposit_hutang_cash');
        $updated = $action->execute($sale->id, $preview['fingerprint']);
        $this->assertSame(1, $updated['settlement_count']);
        $dispatcher = clone BeverageSale::getEventDispatcher();
        BeverageSale::deleting(function (): void {
            throw new \RuntimeException('Simulated failure');
        });
        try {
            $action->execute($sale->id, $updated['fingerprint']);
            $this->fail('Expected database operation failure.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulated failure', $exception->getMessage());
        } finally {
            BeverageSale::setEventDispatcher($dispatcher);
        }
        $this->assertDatabaseCount('beverage_sales', 2);
        $this->assertSame(20, $product->refresh()->stok_sekarang);
        $this->assertSame(20, $this->balance($product, '2026-09-17', 'last'));
        $this->assertSame(7000, $deposit->refresh()->sisa_nominal);
        $this->assertModelExists($change);
    }

    public function test_split_deposit_sale_and_repayment_validation_keep_existing_payment_rules(): void
    {
        $product = $this->product();
        $deposit = DepositBeverage::create(['nama_pelanggan' => 'Pelanggan', 'nominal' => 1000, 'sisa_nominal' => 1000, 'is_used' => false]);
        $this->post(route('admin.beverages.pos.process'), [
            'selected_products' => json_encode([['beverage_id' => $product->id, 'nama_produk' => $product->nama_produk, 'harga_satuan' => 1000, 'jumlah_beli' => 3]]),
            'nama_staff' => 'Admin', 'keterangan_bayar' => 'deposit', 'selected_deposit_id' => $deposit->id,
            'secondary_payment_method' => 'hutang', 'secondary_nama_penghutang' => 'Pelanggan',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $sale = BeverageSale::query()->sole();
        $this->assertSame(2000, $sale->total_harga);
        $this->assertSame(1000, $sale->deposit_amount);
        $this->assertSame(0, $deposit->refresh()->sisa_nominal);
        $this->assertSame(17, $product->refresh()->stok_sekarang);
        Livewire::test('pages::dashboard.admin.beverages.hutang')->call('openConfirmModal', $sale->id)
            ->set('selectedKeteranganBayar', 'operasional')->call('confirmLunas')->assertHasErrors('method');
        $this->assertDatabaseCount('beverage_sales', 1);
        $this->deleteTransaction($sale);
        $this->assertSame(1000, $deposit->refresh()->sisa_nominal);
        $this->assertSame(20, $product->refresh()->stok_sekarang);
    }

    public function test_deleted_sales_and_corrected_snapshots_are_used_in_export(): void
    {
        $product = $this->product();
        $sale = $this->sale($product, 'cash', '2026-09-17 12:00:00');
        $invoice = BeverageInvoice::create(['no_faktur' => 'ARCHIVE', 'tanggal_order' => '2026-09-17', 'status' => 'lunas', 'metode_pembayaran' => 'cash']);
        $invoice->items()->create(['beverage_id' => $product->id, 'nama_barang' => 'Produk', 'qty' => 1, 'harga_perdus' => 1000, 'biaya_ppn' => 0, 'total' => 1000]);
        $this->deleteTransaction($sale);
        $summary = (new BeverageInvoiceExport('2026-09-17', '2026-09-22'))->view()->getData()['stockSummary']['rows'][0];
        $this->assertSame(20, $summary['opening']);
        $this->assertSame(23, $summary['closing']);
        $this->assertSame(0, $summary['sold']);
    }

    public function test_non_admin_cannot_preview_delete_or_bypass_confirmation(): void
    {
        $sale = $this->sale($this->product(), 'hutang');
        $this->actingAs(User::factory()->create(['role' => 'kasir_minum']));
        foreach (['sales', 'hutang'] as $page) {
            Livewire::test('pages::dashboard.admin.beverages.'.$page)->call('confirmDelete', $sale->id)->assertForbidden();
            Livewire::test('pages::dashboard.admin.beverages.'.$page)->call('deleteSale')->assertForbidden();
        }
        $this->assertModelExists($sale);
    }

    private function product(): Beverage
    {
        $product = Beverage::factory()->create(['stok_sekarang' => 20]);
        for ($day = 16; $day <= 23; $day++) {
            foreach ($day === 23 ? ['init'] : ['init', 'last'] as $type) {
                BeverageStokSnapshot::create(['beverage_id' => $product->id, 'tanggal' => '2026-09-'.$day, 'tipe' => $type, 'jumlah' => 20]);
            }
        }

        return $product;
    }

    private function sale(Beverage $product, string $method, string $date = '2026-09-23 10:00:00'): BeverageSale
    {
        return BeverageSale::create(['beverage_id' => $product->id, 'nama_produk' => $product->nama_produk, 'nama_staff' => 'Admin', 'waktu_transaksi' => $date, 'shift' => 'malam', 'jumlah_beli' => 3, 'harga_satuan' => 1000, 'total_harga' => 3000, 'keterangan_bayar' => $method, 'is_lunas' => $method !== 'hutang']);
    }

    private function deleteTransaction(BeverageSale $sale): void
    {
        $action = app(DeleteBeverageSale::class);
        $preview = $action->preview($sale->id);
        $this->assertSame(0, $preview['error_count'], implode(' ', $preview['errors']));
        $this->assertNull($action->execute($sale->id, $preview['fingerprint']));
        $this->assertModelMissing($sale);
    }

    private function balance(Beverage $product, string $date, string $type): int
    {
        return BeverageStokSnapshot::where('beverage_id', $product->id)->where('tanggal', $date)->where('tipe', $type)->sole()->jumlah;
    }
}
