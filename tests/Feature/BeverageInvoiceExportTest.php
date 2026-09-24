<?php

namespace Tests\Feature;

use App\Actions\CancelBeverageInvoice;
use App\Actions\SaveBeverageInvoice;
use App\Exports\BeverageInvoiceExport;
use App\Models\Beverage;
use App\Models\BeverageInvoice;
use App\Models\BeverageRestock;
use App\Models\BeverageSale;
use App\Models\BeverageStokSnapshot;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Tests\TestCase;

class BeverageInvoiceExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('jim_test', DB::connection()->getDatabaseName());
        $this->travelTo(CarbonImmutable::parse('2026-09-23 12:00:00', 'Asia/Jakarta'));
        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    public function test_archive_product_mapping_can_be_changed_and_cleared_without_stock_effects(): void
    {
        $active = Beverage::factory()->create(['stok_sekarang' => 50]);
        $inactive = Beverage::factory()->create(['stok_sekarang' => 70]);
        $inactive->delete();
        $this->snapshot($active, '2026-09-20', 'init', 40);
        $snapshots = BeverageStokSnapshot::all()->toArray();
        $invoice = $this->invoice(null, '2026-09-20');
        $page = Livewire::test('pages::dashboard.admin.beverages.invoice-edit', ['invoice' => $invoice->id])
            ->assertSee('Master Produk')->assertSee($inactive->nama_produk.' (Nonaktif)');
        foreach ([$active->id, $inactive->id, '', $active->id, null] as $id) {
            $page->set('items.0.beverage_id', $id)->call('update')->assertHasNoErrors();
            $this->assertSame($id === '' ? null : $id, $invoice->items()->sole()->beverage_id);
            $this->assertSame('Nama historis', $invoice->items()->sole()->nama_barang);
            $this->assertNull($invoice->refresh()->stock_posted_at);
            $this->assertSame([50, 70], [$active->refresh()->stok_sekarang, $inactive->refresh()->stok_sekarang]);
            $this->assertDatabaseCount('beverage_restocks', 0);
            $this->assertSame($snapshots, BeverageStokSnapshot::all()->toArray());
        }
        $page->set('items.0.beverage_id', 999999)->call('update')->assertHasErrors('items.0.beverage_id');
        $this->assertNull($invoice->items()->sole()->beverage_id);
        $page->set('items.0.beverage_id', $inactive->id)->call('update')->assertHasNoErrors();
        $preview = app(CancelBeverageInvoice::class)->preview($invoice->id);
        app(CancelBeverageInvoice::class)->execute($invoice->id, $preview['fingerprint']);
        $this->assertModelMissing($invoice);
        $this->assertSame(70, $inactive->refresh()->stok_sekarang);
        $this->assertSame($snapshots, BeverageStokSnapshot::all()->toArray());
    }

    public function test_unmapped_archive_items_select_the_closest_active_product_without_persisting(): void
    {
        $product = Beverage::factory()->create(['nama_produk' => 'Crystalin 600 ml', 'stok_sekarang' => 50]);
        Beverage::factory()->create(['nama_produk' => 'CRYSTALIN RUANG STUDIO']);
        $inactive = Beverage::factory()->create(['nama_produk' => 'crystalin']);
        $inactive->delete();
        $invoice = $this->invoice(null, '2026-09-20');
        $invoice->items()->sole()->update(['nama_barang' => 'crystalin']);

        $page = Livewire::test('pages::dashboard.admin.beverages.invoice-edit', ['invoice' => $invoice->id])
            ->assertSet('items.0.beverage_id', $product->id);
        $this->assertNull($invoice->items()->sole()->beverage_id);
        $page->set('items.0.beverage_id', '')->set('diterima_oleh', 'Admin')
            ->assertSet('items.0.beverage_id', '');
        $page->set('items.0.beverage_id', $product->id)->call('update')->assertHasNoErrors();
        $this->assertSame($product->id, $invoice->items()->sole()->beverage_id);
        $this->assertSame('crystalin', $invoice->items()->sole()->nama_barang);
        $this->assertSame(50, $product->refresh()->stok_sekarang);
        $this->assertDatabaseCount('beverage_restocks', 0);
        $this->assertNull($invoice->refresh()->stock_posted_at);
    }

    public function test_product_suggestions_normalize_names_handle_typos_and_preserve_existing_mapping(): void
    {
        $product = Beverage::factory()->create(['nama_produk' => 'Crystalin 600 ml']);
        Beverage::factory()->create(['nama_produk' => 'Crystalin 1500 ml']);
        $invoice = $this->invoice(null, '2026-09-20');
        foreach (['  CRYSTALIN 600-ML  ', 'Crystlain 600 ml'] as $name) {
            $invoice->items()->sole()->update(['nama_barang' => $name]);
            Livewire::test('pages::dashboard.admin.beverages.invoice-edit', ['invoice' => $invoice->id])
                ->assertSet('items.0.beverage_id', $product->id);
        }
        $mapped = Beverage::factory()->create(['nama_produk' => 'Pilihan manual']);
        $mapped->delete();
        $invoice->items()->sole()->update(['beverage_id' => $mapped->id]);
        Livewire::test('pages::dashboard.admin.beverages.invoice-edit', ['invoice' => $invoice->id])
            ->assertSet('items.0.beverage_id', $mapped->id);
    }

    public function test_product_suggestions_leave_missing_weak_ambiguous_and_stock_linked_matches_unchanged(): void
    {
        $invoice = $this->invoice(null, '2026-09-20');
        $invoice->items()->sole()->update(['nama_barang' => 'Crystalin']);
        Livewire::test('pages::dashboard.admin.beverages.invoice-edit', ['invoice' => $invoice->id])
            ->assertSet('items.0.beverage_id', null);
        $product = Beverage::factory()->create(['nama_produk' => 'Crystalin']);
        foreach (['', '---', 'Roti tawar'] as $name) {
            $invoice->items()->sole()->update(['nama_barang' => $name]);
            Livewire::test('pages::dashboard.admin.beverages.invoice-edit', ['invoice' => $invoice->id])
                ->assertSet('items.0.beverage_id', null);
        }
        $invoice->items()->sole()->update(['nama_barang' => 'Crystalin']);
        $duplicate = Beverage::factory()->create(['nama_produk' => $product->nama_produk]);
        Livewire::test('pages::dashboard.admin.beverages.invoice-edit', ['invoice' => $invoice->id])
            ->assertSet('items.0.beverage_id', null);
        $duplicate->delete();
        $invoice->update(['stock_posted_at' => now()]);
        Livewire::test('pages::dashboard.admin.beverages.invoice-edit', ['invoice' => $invoice->id])
            ->assertSet('items.0.beverage_id', null);
    }

    public function test_xlsx_groups_products_and_uses_stock_movements_without_counting_archive_pcs(): void
    {
        $product = Beverage::factory()->create(['nama_produk' => 'Produk A', 'stok_sekarang' => 100]);
        for ($day = 20; $day <= 23; $day++) {
            foreach ($day === 23 ? ['init'] : ['init', 'last'] as $type) {
                $this->snapshot($product, '2026-09-'.$day, $type, 100);
            }
        }
        app(SaveBeverageInvoice::class)->execute([
            'no_faktur' => 'INV-NEW', 'tanggal_order' => '2026-09-20', 'tanggal_menerima' => '2026-09-21',
            'status' => 'pending', 'metode_pembayaran' => 'cash',
            'items' => [['beverage_id' => $product->id, 'total_pcs' => 12, 'qty' => 2, 'harga_perdus' => 1000, 'biaya_ppn' => 0]],
        ]);
        $archive = $this->invoice($product, '2026-09-21');
        $archive->items()->create(['beverage_id' => $product->id, 'nama_barang' => 'Nama lain', 'total_pcs' => 999, 'qty' => 1, 'harga_perdus' => 1000, 'biaya_ppn' => 0, 'total' => 1000]);
        $other = Beverage::factory()->create(['nama_produk' => 'Produk B', 'stok_sekarang' => 40]);
        $other->delete();
        $this->invoice($other, '2026-09-22');
        $this->invoice(null, '2026-09-22');
        $this->snapshot($other, '2026-09-20', 'init', 40);
        $this->snapshot($other, '2026-09-22', 'last', 40);
        $this->restock($product, '2026-09-22', 8);
        $this->restock($product, '2026-09-23', 90);
        $this->restock($product, '2026-09-19', 90);
        foreach (['cash' => 2, 'tf_bca_qris' => 2, 'hutang' => 2, 'deposit' => 2, 'operasional' => 3, 'deposit_hutang_cash' => 99, 'deposit_hutang_qris' => 99, 'pengeluaran_umum' => 99] as $method => $quantity) {
            $this->sale($product, $method, $quantity, '2026-09-22 23:59:59');
        }
        $this->sale($product, 'cash', 50, '2026-09-23 00:00:00');
        $parent = $this->sale($product, 'cash', 50, '2026-09-19 23:59:59');
        $this->sale($product, 'cash', 80, '2026-09-21 12:00:00')->update(['parent_beverage_sale_id' => $parent->id]);
        BeverageStokSnapshot::where('beverage_id', $product->id)->where('tanggal', '2026-09-22')->where('tipe', 'last')->update(['jumlah' => 109]);

        $sheet = $this->xlsx(new BeverageInvoiceExport('2026-09-20', '2026-09-22'));
        $rows = $sheet->toArray(null, true, false);
        $title = array_search('Ringkasan Stok Produk', array_column($rows, 0), true);
        $this->assertIsInt($title);
        $this->assertSame(['Produk', 'Pcs Invoice', 'Stok Awal', 'Ditambah', 'Jumlah Stok', 'Terjual', 'Stok Akhir', 'Operasional'], array_slice($rows[$title + 3], 0, 8));
        $this->assertSame(['Produk A', 2010, 100, 20, 120, 8, 109, 3], array_slice($rows[$title + 4], 0, 8));
        $this->assertSame(['Produk B (Nonaktif)', 999, 40, 0, 40, 0, 40, 0], array_slice($rows[$title + 5], 0, 8));
        $this->assertSame('#,##0', $sheet->getStyle('H'.($title + 5))->getNumberFormat()->getFormatCode());
        $this->assertStringContainsString('1 item belum dipetakan', $rows[$title + 6][0]);
        $this->assertSame('Tanggal Order', $sheet->getCell('A1')->getValue());
        $this->assertSame('Total Pcs', $sheet->getCell('G1')->getValue());
        $this->assertSame('"Rp" #,##0', $sheet->getStyle('H3')->getNumberFormat()->getFormatCode());
        $this->assertSame('#,##0', $sheet->getStyle('B'.($title + 5))->getNumberFormat()->getFormatCode());
        $this->assertSame('n', $sheet->getCell('B'.($title + 5))->getDataType());
        $summary = (new BeverageInvoiceExport('2026-09-20', '2026-09-22'))->view()->getData()['stockSummary'];
        $this->assertCount(2, $summary['rows']);
        $this->assertSame([], $summary['notes']);
    }

    public function test_missing_snapshots_remain_unknown_and_today_uses_current_balance(): void
    {
        $product = Beverage::factory()->create(['stok_sekarang' => 17]);
        $this->invoice($product, '2026-09-20');
        $missing = (new BeverageInvoiceExport('2026-09-20', '2026-09-22'))->view()->getData()['stockSummary'];
        $this->assertNull($missing['rows'][0]['opening']);
        $this->assertNull($missing['rows'][0]['available']);
        $this->assertNull($missing['rows'][0]['closing']);
        $this->assertCount(2, $missing['notes']);
        $this->assertStringContainsString('<td>—</td>', (new BeverageInvoiceExport('2026-09-20', '2026-09-22'))->view()->render());
        $this->snapshot($product, '2026-09-20', 'init', 10);
        $this->snapshot($product, '2026-09-23', 'last', 999);
        $today = (new BeverageInvoiceExport('2026-09-20', '2026-09-23'))->view()->getData()['stockSummary'];
        $this->assertSame(17, $today['rows'][0]['closing']);
        $this->assertStringContainsString('+7 pcs', $today['notes'][0]);
        $this->assertSame(17, $product->refresh()->stok_sekarang);
    }

    public function test_partial_filters_match_the_page_and_infer_missing_period_bounds(): void
    {
        $product = Beverage::factory()->create();
        $first = $this->invoice($product, '2026-09-19');
        $second = $this->invoice($product, '2026-09-20');
        $third = $this->invoice($product, '2026-09-22');
        foreach ([
            ['2026-09-20', '', [$second->id, $third->id], '2026-09-20', '2026-09-22'],
            ['', '2026-09-20', [$first->id, $second->id], '2026-09-19', '2026-09-20'],
            ['', '', [$first->id, $second->id, $third->id], '2026-09-19', '2026-09-22'],
        ] as [$start, $end, $ids, $expectedStart, $expectedEnd]) {
            $data = (new BeverageInvoiceExport($start, $end))->view()->getData();
            $exported = collect($data['monthlyData'])->flatMap(fn ($month) => $month['invoices'])->pluck('id')->sort()->values()->all();
            $this->assertSame($ids, $exported);
            $page = Livewire::test('pages::dashboard.admin.beverages.invoice')->set('startDate', $start)->set('endDate', $end);
            $this->assertSame($ids, $page->get('invoices')->pluck('id')->sort()->values()->all());
            $this->assertSame($expectedStart, $data['stockSummary']['start']);
            $this->assertSame($expectedEnd, $data['stockSummary']['end']);
        }
    }

    public function test_receipt_outside_order_period_does_not_count_as_incoming_and_empty_export_is_valid(): void
    {
        $product = Beverage::factory()->create();
        $invoice = $this->invoice($product, '2026-09-20');
        $invoice->update(['tanggal_menerima' => '2026-09-22']);
        $this->restock($product, '2026-09-22', 24);
        $data = (new BeverageInvoiceExport('2026-09-20', '2026-09-20'))->view()->getData();
        $this->assertCount(1, $data['monthlyData']);
        $this->assertSame(0, $data['stockSummary']['rows'][0]['added']);
        $sheet = $this->xlsx(new BeverageInvoiceExport('2026-09-18', '2026-09-18'));
        $this->assertContains('Tidak ada data untuk periode ini.', array_column($sheet->toArray(), 0));
        $this->assertContains('Tidak ada produk terpetakan pada invoice hasil ekspor.', array_column($sheet->toArray(), 0));
    }

    public function test_summary_query_count_does_not_grow_with_product_count(): void
    {
        $this->invoice(Beverage::factory()->create(), '2026-09-20');
        DB::enableQueryLog();
        (new BeverageInvoiceExport('2026-09-20', '2026-09-22'))->view()->render();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();
        foreach (range(1, 8) as $index) {
            $this->invoice(Beverage::factory()->create(), '2026-09-20');
        }
        DB::flushQueryLog();
        DB::enableQueryLog();
        (new BeverageInvoiceExport('2026-09-20', '2026-09-22'))->view()->render();
        $this->assertSame($count, count(DB::getQueryLog()));
        DB::disableQueryLog();
    }

    private function invoice(?Beverage $product, string $date): BeverageInvoice
    {
        $invoice = BeverageInvoice::create(['no_faktur' => fake()->unique()->uuid(), 'tanggal_order' => $date, 'tanggal_menerima' => $date, 'status' => 'lunas', 'metode_pembayaran' => 'cash']);
        $invoice->items()->create(['beverage_id' => $product?->id, 'nama_barang' => 'Nama historis', 'total_pcs' => 999, 'qty' => 1, 'harga_perdus' => 1000, 'biaya_ppn' => 0, 'total' => 1000]);

        return $invoice;
    }

    private function snapshot(Beverage $product, string $date, string $type, int $quantity): void
    {
        BeverageStokSnapshot::create(['beverage_id' => $product->id, 'tanggal' => $date, 'tipe' => $type, 'jumlah' => $quantity]);
    }

    private function restock(Beverage $product, string $date, int $quantity): void
    {
        BeverageRestock::create(['beverage_id' => $product->id, 'tanggal' => $date, 'tipe' => 'restock', 'jumlah_tambah' => $quantity]);
    }

    private function sale(Beverage $product, string $method, int $quantity, string $date): BeverageSale
    {
        return BeverageSale::create(['beverage_id' => $product->id, 'nama_produk' => $product->nama_produk, 'nama_staff' => 'Admin', 'waktu_transaksi' => $date, 'shift' => $method === 'cash' ? 'pagi' : 'malam', 'jumlah_beli' => $quantity, 'harga_satuan' => 1000, 'total_harga' => $quantity * 1000, 'keterangan_bayar' => $method]);
    }

    private function xlsx(BeverageInvoiceExport $export): Worksheet
    {
        $path = tempnam(sys_get_temp_dir(), 'invoice-export-');
        try {
            file_put_contents($path, Excel::raw($export, ExcelFormat::XLSX));

            return IOFactory::load($path)->getActiveSheet();
        } finally {
            unlink($path);
        }
    }
}
