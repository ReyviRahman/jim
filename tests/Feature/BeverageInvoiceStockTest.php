<?php

namespace Tests\Feature;

use App\Actions\CancelBeverageInvoice;
use App\Actions\SaveBeverageInvoice;
use App\Exports\BeverageInvoiceExport;
use App\Models\Beverage;
use App\Models\BeverageInvoice;
use App\Models\BeverageRestock;
use App\Models\BeverageStokSnapshot;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class BeverageInvoiceStockTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-09-23 12:00:00', 'Asia/Jakarta'));
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        Storage::fake('local');
    }

    public function test_create_posts_duplicate_product_items_once_and_corrects_only_later_snapshots(): void
    {
        $product = $this->product();
        $other = $this->product();
        $data = $this->data($product);
        $data['items'][] = $data['items'][0];
        $data['items'][] = $this->item($other, 5);
        $invoice = app(SaveBeverageInvoice::class)->execute($data);
        $this->assertNotNull($invoice->stock_posted_at);
        $this->assertSame(124, $product->refresh()->stok_sekarang);
        $this->assertSame(105, $other->refresh()->stok_sekarang);
        $this->assertSame(100, $this->balance($product, '2026-09-19', 'last'));
        $this->assertSame(100, $this->balance($product, '2026-09-20', 'init'));
        $this->assertSame(124, $this->balance($product, '2026-09-20', 'last'));
        $this->assertSame(124, $this->balance($product, '2026-09-23', 'init'));
        $this->assertDatabaseCount('beverage_restocks', 3);
        $this->assertSame(12500, $invoice->items()->first()->total);
        try {
            app(SaveBeverageInvoice::class)->execute($data);
            $this->fail('Duplicate invoice must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('no_faktur', $exception->errors());
        }
        $this->assertSame(124, $product->refresh()->stok_sekarang);
    }

    public function test_livewire_create_and_locked_edit_render_and_recalculate_totals(): void
    {
        $product = $this->product();
        $page = Livewire::test('pages::dashboard.admin.beverages.invoice-create');
        foreach ($this->data($product) as $key => $value) {
            $page->set($key, $value);
        }
        $page->set('image', UploadedFile::fake()->image('invoice.png'))
            ->call('store')->assertHasNoErrors()->assertRedirect(route('admin.beverages.invoice'));
        $invoice = BeverageInvoice::query()->sole();
        Storage::disk('local')->assertExists($invoice->image_path);
        $edit = Livewire::test('pages::dashboard.admin.beverages.invoice-edit', ['invoice' => $invoice->id]);
        $edit->assertSee('dikunci')->set('items.0.qty', 3)->set('items.0.total', 1)
            ->set('status', 'lunas')->call('update')->assertHasNoErrors();
        $this->assertSame(18500, $invoice->items()->sole()->total);
        $this->assertSame(112, $product->refresh()->stok_sekarang);
        $edit->set('items.0.total_pcs', 99)->call('update')->assertHasErrors('items');
        $this->assertSame(12, $invoice->items()->sole()->total_pcs);
    }

    public function test_server_rejects_locked_field_and_foreign_item_changes(): void
    {
        $product = $this->product();
        $invoice = app(SaveBeverageInvoice::class)->execute($this->data($product));
        $data = $this->data($product);
        $data['items'] = $invoice->items->toArray();
        $changes = [
            ['tanggal_menerima' => '2026-09-21'],
            ['items' => [array_replace($data['items'][0], ['beverage_id' => 9999])]],
            ['items' => [$data['items'][0], $data['items'][0]]],
            ['items' => [array_replace($data['items'][0], ['id' => 9999])]],
        ];
        foreach ($changes as $change) {
            try {
                app(SaveBeverageInvoice::class)->execute(array_replace($data, $change), null, $invoice->id);
                $this->fail('Stock fields must remain locked.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('items', $exception->errors());
            }
        }
        $this->assertSame(112, $product->refresh()->stok_sekarang);
    }

    public function test_missing_snapshots_block_creation_and_clean_up_uploaded_image(): void
    {
        $product = $this->product();
        BeverageStokSnapshot::query()->where('beverage_id', $product->id)->where('tanggal', '2026-09-21')->where('tipe', 'last')->delete();
        try {
            app(SaveBeverageInvoice::class)->execute($this->data($product), UploadedFile::fake()->image('invoice.jpg'));
            $this->fail('Missing snapshots must block posting.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('2026-09-21', implode(' ', $exception->errors()['stock']));
        }
        $this->assertDatabaseCount('beverage_invoices', 0);
        $this->assertDatabaseCount('beverage_restocks', 0);
        $this->assertSame(100, $product->refresh()->stok_sekarang);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_cancel_preview_is_paginated_and_repeated_deletion_is_harmless(): void
    {
        $product = $this->product();
        $data = $this->data($product);
        $data['items'][] = $this->item($this->product(), 5);
        $invoice = app(SaveBeverageInvoice::class)->execute($data, UploadedFile::fake()->image('invoice.jpg'));
        $image = $invoice->image_path;
        $product->delete();
        $page = Livewire::test('pages::dashboard.admin.beverages.invoice');
        $page->call('confirmDelete', $invoice->id)->assertSee('kurangi 12 pcs');
        $this->assertSame(10, $page->get('snapshotChanges')->count());
        $page->call('changeImpactPage', 1);
        $this->assertSame(2, $page->get('snapshotChanges')->count());
        $page->call('deleteInvoice')->assertHasNoErrors();
        $this->assertModelMissing($invoice);
        $this->assertSame(100, $product->refresh()->stok_sekarang);
        $this->assertSame(100, $this->balance($product, '2026-09-21', 'init'));
        $this->assertDatabaseCount('beverage_restocks', 0);
        Storage::disk('local')->assertMissing($image);
        $this->assertNull(app(CancelBeverageInvoice::class)->execute($invoice->id, 'already-deleted'));
        $this->assertSame(100, $product->refresh()->stok_sekarang);
    }

    public function test_changed_preview_requires_confirmation_and_negative_snapshots_block_cancellation(): void
    {
        $product = $this->product();
        $invoice = app(SaveBeverageInvoice::class)->execute($this->data($product));
        $action = app(CancelBeverageInvoice::class);
        $preview = $action->preview($invoice->id);
        $product->increment('stok_sekarang', 1);
        $updated = $action->execute($invoice->id, $preview['fingerprint']);
        $this->assertNotNull($updated);
        $this->assertModelExists($invoice);
        BeverageStokSnapshot::query()->where('beverage_id', $product->id)->where('tanggal', '2026-09-21')->where('tipe', 'init')->update(['jumlah' => 2]);
        $blocked = $action->execute($invoice->id, $updated['fingerprint']);
        $this->assertGreaterThan(0, $blocked['error_count']);
        $this->assertSame(113, $product->refresh()->stok_sekarang);
        $this->assertDatabaseCount('beverage_restocks', 1);
    }

    public function test_invoice_restocks_cannot_be_edited_or_deleted_directly(): void
    {
        $product = $this->product();
        app(SaveBeverageInvoice::class)->execute($this->data($product));
        $restock = BeverageRestock::query()->sole();
        Livewire::test('pages::dashboard.admin.beverages.restock')
            ->call('edit', $restock->id)->assertHasErrors('stock')
            ->set('editingId', $restock->id)->set('edit_jumlah_tambah', 1)->call('update')->assertHasErrors('stock')
            ->set('deleteId', $restock->id)->call('executeDelete')->assertHasErrors('stock');
        $this->assertModelExists($restock);
        $this->assertSame(112, $product->refresh()->stok_sekarang);
    }

    public function test_legacy_invoice_edit_and_delete_do_not_change_stock(): void
    {
        $product = $this->product();
        $data = $this->data($product);
        $invoice = BeverageInvoice::create(collect($data)->except('items')->all());
        $invoice->items()->create(['nama_barang' => 'Barang lama', 'qty' => 1, 'harga_perdus' => 1000, 'biaya_ppn' => 0, 'total' => 1000]);
        $data['items'] = $invoice->items->toArray();
        $data['items'][0]['qty'] = 2;
        $data['items'][0]['total_pcs'] = 48;
        $data['items'][0]['beverage_id'] = $product->id;
        $data['stock_posted_at'] = now()->toDateTimeString();
        $snapshots = BeverageStokSnapshot::query()->orderBy('id')->get()->toArray();
        app(SaveBeverageInvoice::class)->execute($data, null, $invoice->id);
        $this->assertNull($invoice->refresh()->stock_posted_at);
        $this->assertSame(48, $invoice->items()->sole()->total_pcs);
        $this->assertSame($product->id, $invoice->items()->sole()->beverage_id);
        $preview = app(CancelBeverageInvoice::class)->preview($invoice->id);
        $this->assertTrue($preview['legacy']);
        app(CancelBeverageInvoice::class)->execute($invoice->id, $preview['fingerprint']);
        $this->assertSame(100, $product->refresh()->stok_sekarang);
        $this->assertDatabaseCount('beverage_restocks', 0);
        $this->assertSame($snapshots, BeverageStokSnapshot::query()->orderBy('id')->get()->toArray());
    }

    public function test_archive_pcs_can_be_filled_corrected_and_cleared_without_stock_changes(): void
    {
        $product = $this->product();
        $invoice = BeverageInvoice::create(collect($this->data($product))->except('items')->all());
        $invoice->items()->create(['nama_barang' => 'Barang lama', 'qty' => 1, 'harga_perdus' => 1000, 'biaya_ppn' => 0, 'total' => 1000]);
        $snapshots = BeverageStokSnapshot::query()->orderBy('id')->get()->toArray();
        $page = Livewire::test('pages::dashboard.admin.beverages.invoice-edit', ['invoice' => $invoice->id])
            ->assertSee('pengisian total pcs hanya melengkapi informasi pembelian dan tidak mengubah stok.');

        foreach ([48, 72, '', 24, null] as $pcs) {
            $page->set('items.0.total_pcs', $pcs)->call('update')->assertHasNoErrors();
            $this->assertSame($pcs === '' ? null : $pcs, $invoice->items()->sole()->total_pcs);
            $this->assertNull($invoice->refresh()->stock_posted_at);
            $this->assertSame(100, $product->refresh()->stok_sekarang);
            $this->assertDatabaseCount('beverage_restocks', 0);
            $this->assertSame($snapshots, BeverageStokSnapshot::query()->orderBy('id')->get()->toArray());
            $html = (new BeverageInvoiceExport('2026-09-20', '2026-09-20'))->view()->render();
            $this->assertStringContainsString('<td>'.($pcs === '' || $pcs === null ? '—' : $pcs).'</td>', $html);
        }

        foreach ([0, -1, 1.5] as $pcs) {
            $page->set('items.0.total_pcs', $pcs)->call('update')->assertHasErrors('items.0.total_pcs');
            $this->assertNull($invoice->items()->sole()->total_pcs);
        }
        $this->assertSame(100, $product->refresh()->stok_sekarang);
        $this->assertDatabaseCount('beverage_restocks', 0);
        $this->assertSame($snapshots, BeverageStokSnapshot::query()->orderBy('id')->get()->toArray());
    }

    public function test_private_photo_access_replacement_and_export(): void
    {
        $product = $this->product();
        $invoice = app(SaveBeverageInvoice::class)->execute($this->data($product), UploadedFile::fake()->image('invoice.jpg'));
        $old = $invoice->image_path;
        $this->get(route('admin.beverages.invoice.image', $invoice))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $data = $this->data($product);
        $data['items'] = $invoice->items->toArray();
        $invoice = app(SaveBeverageInvoice::class)->execute($data, UploadedFile::fake()->image('new.png'), $invoice->id);
        Storage::disk('local')->assertMissing($old);
        Storage::disk('local')->assertExists($invoice->image_path);
        $html = (new BeverageInvoiceExport('2026-09-20', '2026-09-20'))->view()->render();
        $this->assertStringContainsString('Qty Dus', $html);
        $this->assertStringContainsString('Total Pcs', $html);
        $this->assertStringContainsString($product->nama_produk, $html);
        $this->actingAs(User::factory()->create(['role' => 'member']));
        $this->get(route('admin.beverages.invoice.image', $invoice))->assertRedirect(route('home'));
        Livewire::test('pages::dashboard.admin.beverages.invoice-edit', ['invoice' => $invoice->id])->assertForbidden();
        Livewire::test('pages::dashboard.admin.beverages.invoice')->call('confirmDelete', $invoice->id)->assertForbidden();
    }

    public function test_invalid_upload_dates_and_quantities_are_rejected(): void
    {
        Livewire::test('pages::dashboard.admin.beverages.invoice-create')
            ->set('image', UploadedFile::fake()->create('bad.pdf', 100, 'application/pdf'))->assertHasErrors('image');
        Livewire::test('pages::dashboard.admin.beverages.invoice-create')
            ->set('image', UploadedFile::fake()->image('large.jpg')->size(10241))->assertHasErrors('image');
        $product = $this->product();
        foreach ([
            ['tanggal_menerima' => '2026-09-24'],
            ['tanggal_menerima' => '2026-09-19'],
            ['items' => [$this->item($product, 0)]],
        ] as $change) {
            try {
                app(SaveBeverageInvoice::class)->execute(array_replace($this->data($product), $change));
                $this->fail('Invalid invoice accepted.');
            } catch (ValidationException $exception) {
                $this->assertNotEmpty($exception->errors());
            }
        }
        $this->assertDatabaseCount('beverage_invoices', 0);
    }

    public function test_failed_file_write_does_not_create_invoice_or_stock(): void
    {
        $product = $this->product();
        $disk = \Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('putFileAs')->once()->andReturn(false);
        Storage::shouldReceive('disk')->with('local')->andReturn($disk);
        try {
            app(SaveBeverageInvoice::class)->execute($this->data($product), UploadedFile::fake()->image('invoice.jpg'));
            $this->fail('Failed upload must stop invoice creation.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('image', $exception->errors());
        }
        $this->assertDatabaseCount('beverage_invoices', 0);
        $this->assertSame(100, $product->refresh()->stok_sekarang);
    }

    public function test_posting_today_requires_init_but_not_last_and_does_not_change_init(): void
    {
        $product = $this->product();
        $data = $this->data($product);
        $data['tanggal_menerima'] = '2026-09-23';
        app(SaveBeverageInvoice::class)->execute($data);
        $this->assertSame(112, $product->refresh()->stok_sekarang);
        $this->assertSame(100, $this->balance($product, '2026-09-23', 'init'));
        $this->assertDatabaseMissing('beverage_stok_snapshots', ['beverage_id' => $product->id, 'tanggal' => '2026-09-23', 'tipe' => 'last']);
    }

    public function test_new_zero_stock_product_can_receive_an_invoice_today(): void
    {
        Livewire::test('pages::dashboard.admin.beverages.create')
            ->set('nama_produk', 'Produk baru')->set('harga_modal', 1000)->set('harga_jual', 2000)
            ->set('stok_awal', 0)->call('store')->assertHasNoErrors();
        $product = Beverage::query()->sole();
        $data = $this->data($product);
        $data['tanggal_menerima'] = '2026-09-23';
        app(SaveBeverageInvoice::class)->execute($data);
        $this->assertSame(12, $product->refresh()->stok_sekarang);
        $this->assertSame(0, $this->balance($product, '2026-09-23', 'init'));
    }

    public function test_database_failure_rolls_back_posting_and_cleans_image(): void
    {
        $product = $this->product();
        $dispatcher = clone BeverageRestock::getEventDispatcher();
        BeverageRestock::creating(function (): void {
            throw new \RuntimeException('Simulated storage failure');
        });
        try {
            app(SaveBeverageInvoice::class)->execute($this->data($product), UploadedFile::fake()->image('invoice.jpg'));
            $this->fail('The transaction should fail.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulated storage failure', $exception->getMessage());
        } finally {
            BeverageRestock::setEventDispatcher($dispatcher);
        }
        $this->assertDatabaseCount('beverage_invoices', 0);
        $this->assertDatabaseCount('beverage_invoice_items', 0);
        $this->assertSame(100, $product->refresh()->stok_sekarang);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_failed_deletion_rolls_back_snapshot_changes_and_keeps_photo(): void
    {
        $product = $this->product();
        $invoice = app(SaveBeverageInvoice::class)->execute($this->data($product), UploadedFile::fake()->image('invoice.jpg'));
        $preview = app(CancelBeverageInvoice::class)->preview($invoice->id);
        $dispatcher = clone BeverageInvoice::getEventDispatcher();
        BeverageInvoice::deleting(function (): void {
            throw new \RuntimeException('Simulated delete failure');
        });
        try {
            app(CancelBeverageInvoice::class)->execute($invoice->id, $preview['fingerprint']);
            $this->fail('The transaction should fail.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulated delete failure', $exception->getMessage());
        } finally {
            BeverageInvoice::setEventDispatcher($dispatcher);
        }
        $this->assertModelExists($invoice);
        $this->assertDatabaseCount('beverage_restocks', 1);
        $this->assertSame(112, $product->refresh()->stok_sekarang);
        $this->assertSame(112, $this->balance($product, '2026-09-21', 'init'));
        Storage::disk('local')->assertExists($invoice->image_path);
    }

    private function product(): Beverage
    {
        $product = Beverage::factory()->create(['stok_sekarang' => 100]);
        for ($day = 19; $day <= 23; $day++) {
            foreach ($day === 23 ? ['init'] : ['init', 'last'] as $type) {
                BeverageStokSnapshot::create(['beverage_id' => $product->id, 'tanggal' => '2026-09-'.$day, 'tipe' => $type, 'jumlah' => 100]);
            }
        }

        return $product;
    }

    /** @return array<string, mixed> */
    private function data(Beverage $product): array
    {
        return ['no_faktur' => 'INV-TEST', 'tanggal_order' => '2026-09-20', 'tanggal_menerima' => '2026-09-20', 'diterima_oleh' => 'Admin', 'status' => 'pending', 'metode_pembayaran' => 'cash', 'items' => [$this->item($product, 12)]];
    }

    /** @return array<string, mixed> */
    private function item(Beverage $product, int $pcs): array
    {
        return ['beverage_id' => $product->id, 'total_pcs' => $pcs, 'qty' => 2, 'harga_perdus' => 6000, 'biaya_ppn' => 500, 'total' => 1];
    }

    private function balance(Beverage $product, string $date, string $type): int
    {
        return BeverageStokSnapshot::query()->where('beverage_id', $product->id)->where('tanggal', $date)->where('tipe', $type)->sole()->jumlah;
    }
}
