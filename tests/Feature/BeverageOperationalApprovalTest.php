<?php

namespace Tests\Feature;

use App\Actions\BeverageOperationalApproval;
use App\Actions\DeleteBeverageSale;
use App\Models\Beverage;
use App\Models\BeverageSale;
use App\Models\BeverageStokSnapshot;
use App\Models\DepositBeverage;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class BeverageOperationalApprovalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-09-25 12:00:00', 'Asia/Jakarta'));
    }

    public function test_pending_request_does_not_change_stock_or_create_sales(): void
    {
        $product = $this->product();
        $cashier = User::factory()->create(['role' => 'kasir_minum']);
        $request = app(BeverageOperationalApproval::class)->submit($cashier, $this->input($product));
        $this->assertSame('pending', $request->status);
        $this->assertSame($cashier->id, $request->requested_by);
        $this->assertSame(5000, $request->items->first()->harga_satuan);
        $this->assertSame(10, $product->refresh()->stok_sekarang);
        $this->assertDatabaseCount('beverage_sales', 0);
    }

    public function test_admin_auto_approval_is_idempotent(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $product = $this->product();
        $action = app(BeverageOperationalApproval::class);
        $request = $action->submit($admin, $this->input($product));
        $this->assertSame('approved', $request->refresh()->status);
        $this->assertSame($admin->id, $request->decided_by);
        $action->approve($admin, $request->id);
        $this->assertDatabaseCount('beverage_sales', 1);
        $this->assertSame(8, $product->refresh()->stok_sekarang);
        $this->assertSame(10000, BeverageSale::first()->total_harga);
    }

    public function test_only_admin_can_approve_or_reject(): void
    {
        $cashier = User::factory()->create(['role' => 'kasir_gym']);
        $product = $this->product();
        $action = app(BeverageOperationalApproval::class);
        $request = $action->submit($cashier, $this->input($product));
        foreach (['approve', 'reject'] as $method) {
            try {
                if ($method === 'approve') {
                    $action->approve($cashier, $request->id);
                } else {
                    $action->reject($cashier, $request->id, 'Tidak sesuai');
                }
                $this->fail('Cashier cannot decide approval.');
            } catch (HttpException $exception) {
                $this->assertSame(403, $exception->getStatusCode());
            }
        }
        $admin = User::factory()->create(['role' => 'admin']);
        $action->reject($admin, $request->id, 'Tidak sesuai kebutuhan');
        $this->assertSame('rejected', $request->refresh()->status);
        $this->assertSame($admin->id, $request->decided_by);
        $this->assertSame(10, $product->refresh()->stok_sekarang);
        $this->assertDatabaseCount('beverage_sales', 0);
    }

    public function test_historical_approval_updates_snapshots_and_preserves_date(): void
    {
        $product = $this->product();
        $action = app(BeverageOperationalApproval::class);
        $request = $action->submit(User::factory()->create(['role' => 'kasir_minum']), $this->input($product));
        foreach ([['2026-09-25', 'init'], ['2026-09-25', 'last'], ['2026-09-26', 'init']] as [$date, $type]) {
            BeverageStokSnapshot::create(['beverage_id' => $product->id, 'tanggal' => $date, 'tipe' => $type, 'jumlah' => 10]);
        }
        $this->travelTo(CarbonImmutable::parse('2026-09-26 12:00:00', 'Asia/Jakarta'));
        $action->approve(User::factory()->create(['role' => 'admin']), $request->id);
        $this->assertSame('2026-09-25', BeverageSale::first()->waktu_transaksi->toDateString());
        $this->assertSame(10, (int) BeverageStokSnapshot::where('tanggal', '2026-09-25')->where('tipe', 'init')->value('jumlah'));
        $this->assertSame(8, (int) BeverageStokSnapshot::where('tanggal', '2026-09-25')->where('tipe', 'last')->value('jumlah'));
        $this->assertSame(8, (int) BeverageStokSnapshot::where('tanggal', '2026-09-26')->where('tipe', 'init')->value('jumlah'));
        $this->assertSame(8, $product->refresh()->stok_sekarang);
    }

    public function test_insufficient_deposit_rolls_back_approval(): void
    {
        $product = $this->product();
        $deposit = DepositBeverage::create(['nama_pelanggan' => 'Pelanggan', 'nominal' => 4000, 'sisa_nominal' => 4000, 'is_used' => false]);
        $action = app(BeverageOperationalApproval::class);
        $request = $action->submit(User::factory()->create(['role' => 'kasir_minum']), array_replace($this->input($product), [
            'keterangan_bayar' => 'deposit', 'selected_deposit_id' => $deposit->id, 'secondary_payment_method' => 'operasional',
        ]));
        $this->assertSame(4000, $deposit->refresh()->sisa_nominal);
        $deposit->update(['sisa_nominal' => 1000]);
        try {
            $action->approve(User::factory()->create(['role' => 'admin']), $request->id);
            $this->fail('Approval requires sufficient deposit.');
        } catch (ValidationException $exception) {
            $this->assertNotEmpty($exception->errors());
        }
        $this->assertSame('pending', $request->refresh()->status);
        $this->assertSame(1000, $deposit->refresh()->sisa_nominal);
        $this->assertSame(10, $product->refresh()->stok_sekarang);
        $this->assertDatabaseCount('beverage_sales', 0);
    }

    public function test_controller_routes_operational_payment_to_pending_request(): void
    {
        $product = $this->product();
        $data = $this->input($product);
        $data['selected_products'] = json_encode($data['selected_products']);
        $this->actingAs(User::factory()->create(['role' => 'kasir_minum']))
            ->post(route('admin.beverages.pos.process'), $data)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('beverage_operational_requests', ['status' => 'pending']);
        $this->assertDatabaseCount('beverage_sales', 0);
        $this->assertSame(10, $product->refresh()->stok_sekarang);
    }

    public function test_insufficient_current_stock_preserves_pending_request(): void
    {
        $product = $this->product();
        $action = app(BeverageOperationalApproval::class);
        $request = $action->submit(User::factory()->create(['role' => 'kasir_minum']), $this->input($product));
        $product->update(['stok_sekarang' => 1]);
        try {
            $action->approve(User::factory()->create(['role' => 'admin']), $request->id);
            $this->fail('Insufficient stock must prevent approval.');
        } catch (ValidationException $exception) {
            $this->assertNotEmpty($exception->errors());
        }
        $this->assertSame('pending', $request->refresh()->status);
        $this->assertSame(1, $product->refresh()->stok_sekarang);
        $this->assertDatabaseCount('beverage_sales', 0);
    }

    public function test_rejection_requires_reason_and_rejected_request_cannot_be_approved(): void
    {
        $product = $this->product();
        $action = app(BeverageOperationalApproval::class);
        $admin = User::factory()->create(['role' => 'admin']);
        $request = $action->submit(User::factory()->create(['role' => 'kasir_minum']), $this->input($product));
        try {
            $action->reject($admin, $request->id, '   ');
            $this->fail('Rejection must require reason.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('reason', $exception->errors());
        }
        $this->assertSame('pending', $request->refresh()->status);
        $action->reject($admin, $request->id, 'Tidak diperlukan');
        try {
            $action->approve($admin, $request->id);
            $this->fail('Rejected request cannot be approved.');
        } catch (ValidationException $exception) {
            $this->assertNotEmpty($exception->errors());
        }
        $this->assertSame('rejected', $request->refresh()->status);
        $this->assertDatabaseCount('beverage_sales', 0);
        $this->assertSame(10, $product->refresh()->stok_sekarang);
    }

    public function test_pos_scopes_requests_to_cashier_and_shows_all_to_admin(): void
    {
        $product = $this->product();
        $cashier = User::factory()->create(['role' => 'kasir_minum']);
        $other = User::factory()->create(['role' => 'kasir_gym']);
        $action = app(BeverageOperationalApproval::class);
        $action->submit($cashier, array_replace($this->input($product), ['reason' => 'Own operational request']));
        $action->submit($other, array_replace($this->input($product), ['reason' => 'Other operational request']));
        Livewire::actingAs($cashier)->test('pages::dashboard.admin.beverages.pos')
            ->assertSee('Own operational request')->assertDontSee('Other operational request')->assertDontSee('Tolak pengajuan');
        Livewire::actingAs(User::factory()->create(['role' => 'admin']))->test('pages::dashboard.admin.beverages.pos')
            ->assertSee('Own operational request')->assertSee('Other operational request')->assertSee('Tolak pengajuan');
    }

    public function test_livewire_decision_methods_reject_cashier(): void
    {
        $cashier = User::factory()->create(['role' => 'kasir_minum']);
        $request = app(BeverageOperationalApproval::class)->submit($cashier, $this->input($this->product()));
        Livewire::actingAs($cashier)->test('pages::dashboard.admin.beverages.pos')
            ->call('approveOperational', $request->id)->assertForbidden();
        Livewire::actingAs($cashier)->test('pages::dashboard.admin.beverages.pos')
            ->set('rejectionReasons', [$request->id => 'Denied'])->call('rejectOperational', $request->id)->assertForbidden();
        $this->assertSame('pending', $request->refresh()->status);
        $this->assertDatabaseCount('beverage_sales', 0);
    }

    public function test_livewire_process_sale_cannot_bypass_operational_approval(): void
    {
        $product = $this->product();
        Livewire::actingAs(User::factory()->create(['role' => 'kasir_minum']))->test('pages::dashboard.admin.beverages.pos')
            ->set('selectedProducts', $this->input($product)['selected_products'])
            ->set('keterangan_bayar', 'operasional')->set('reason', 'Operational via Livewire')
            ->call('processSale')->assertHasNoErrors();
        $this->assertDatabaseHas('beverage_operational_requests', ['status' => 'pending', 'reason' => 'Operational via Livewire']);
        $this->assertDatabaseCount('beverage_sales', 0);
        $this->assertSame(10, $product->refresh()->stok_sekarang);
    }

    public function test_http_split_operational_payment_remains_pending_without_using_deposit(): void
    {
        $product = $this->product();
        $deposit = DepositBeverage::create(['nama_pelanggan' => 'Pelanggan', 'nominal' => 4000, 'sisa_nominal' => 4000, 'is_used' => false]);
        $data = array_replace($this->input($product), [
            'selected_products' => json_encode($this->input($product)['selected_products']),
            'keterangan_bayar' => 'deposit', 'secondary_payment_method' => 'operasional', 'selected_deposit_id' => $deposit->id,
        ]);
        $this->actingAs(User::factory()->create(['role' => 'kasir_minum']))
            ->post(route('admin.beverages.pos.process'), $data)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('beverage_operational_requests', ['status' => 'pending', 'deposit_amount' => 4000]);
        $this->assertDatabaseCount('beverage_sales', 0);
        $this->assertSame(4000, $deposit->refresh()->sisa_nominal);
        $this->assertSame(10, $product->refresh()->stok_sekarang);
    }

    public function test_missing_historical_snapshots_block_approval_without_changes(): void
    {
        $product = $this->product();
        $action = app(BeverageOperationalApproval::class);
        $request = $action->submit(User::factory()->create(['role' => 'kasir_minum']), $this->input($product));
        $this->travelTo(CarbonImmutable::parse('2026-09-26 12:00:00', 'Asia/Jakarta'));
        try {
            $action->approve(User::factory()->create(['role' => 'admin']), $request->id);
            $this->fail('Missing snapshots must prevent historical approval.');
        } catch (ValidationException $exception) {
            $this->assertNotEmpty($exception->errors());
        }
        $this->assertSame('pending', $request->refresh()->status);
        $this->assertDatabaseCount('beverage_sales', 0);
        $this->assertSame(10, $product->refresh()->stok_sekarang);
    }

    public function test_approved_sale_deletion_restores_stock_and_deposit_without_reopening_request(): void
    {
        $product = $this->product();
        BeverageStokSnapshot::create(['beverage_id' => $product->id, 'tanggal' => '2026-09-25', 'tipe' => 'init', 'jumlah' => 10]);
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);
        $deposit = DepositBeverage::create(['nama_pelanggan' => 'Pelanggan', 'nominal' => 4000, 'sisa_nominal' => 4000, 'is_used' => false]);
        $request = app(BeverageOperationalApproval::class)->submit($admin, array_replace($this->input($product), [
            'keterangan_bayar' => 'deposit', 'selected_deposit_id' => $deposit->id, 'secondary_payment_method' => 'operasional',
        ]));
        $sale = BeverageSale::first();
        $action = app(DeleteBeverageSale::class);
        $preview = $action->preview($sale->id);
        $this->assertSame(0, $preview['error_count']);
        $this->assertNull($action->execute($sale->id, $preview['fingerprint']));
        $this->assertModelMissing($sale);
        $this->assertSame(10, $product->refresh()->stok_sekarang);
        $this->assertSame(4000, $deposit->refresh()->sisa_nominal);
        $this->assertSame('approved', $request->refresh()->status);
        app(BeverageOperationalApproval::class)->approve($admin, $request->id);
        $this->assertDatabaseCount('beverage_sales', 0);
    }

    public function test_deposit_allocation_preserves_totals_for_low_price_items(): void
    {
        $products = Beverage::factory()->count(6)->create(['harga_jual' => 1, 'stok_sekarang' => 10]);
        $deposit = DepositBeverage::create(['nama_pelanggan' => 'Pelanggan', 'nominal' => 5, 'sisa_nominal' => 5, 'is_used' => false]);
        app(BeverageOperationalApproval::class)->submit(User::factory()->create(['role' => 'admin']), [
            'selected_products' => $products->map(fn (Beverage $product): array => ['beverage_id' => $product->id, 'jumlah_beli' => 1])->all(),
            'reason' => 'Operational allocation', 'keterangan_bayar' => 'deposit',
            'selected_deposit_id' => $deposit->id, 'secondary_payment_method' => 'operasional',
        ]);
        $sales = BeverageSale::all();
        $this->assertCount(6, $sales);
        $this->assertSame(5, $sales->sum('deposit_amount'));
        $this->assertSame(1, $sales->sum('total_harga'));
        $this->assertTrue($sales->every(fn (BeverageSale $sale): bool => $sale->total_harga >= 0 && $sale->deposit_amount <= $sale->harga_satuan));
        $this->assertSame(0, $deposit->refresh()->sisa_nominal);
    }

    public function test_owner_and_admin_can_permanently_delete_pending_requests_without_stock_or_deposit_changes(): void
    {
        $product = $this->product();
        $owner = User::factory()->create(['role' => 'kasir_minum']);
        $admin = User::factory()->create(['role' => 'admin']);
        $deposit = DepositBeverage::create(['nama_pelanggan' => 'Pelanggan', 'nominal' => 4000, 'sisa_nominal' => 4000, 'is_used' => false]);
        foreach ([$owner, $admin] as $actor) {
            $request = app(BeverageOperationalApproval::class)->submit($owner, array_replace($this->input($product), [
                'keterangan_bayar' => 'deposit', 'selected_deposit_id' => $deposit->id,
            ]));
            Livewire::actingAs($actor)->test('pages::dashboard.admin.beverages.pos')
                ->call('deleteOperational', $request->id)->assertHasNoErrors();
            $this->assertModelMissing($request);
            $this->assertDatabaseMissing('beverage_operational_request_items', ['request_id' => $request->id]);
        }
        $this->assertSame(10, $product->refresh()->stok_sekarang);
        $this->assertSame(4000, $deposit->refresh()->sisa_nominal);
        $this->assertDatabaseCount('beverage_sales', 0);
    }

    public function test_another_cashier_cannot_delete_pending_request(): void
    {
        $request = app(BeverageOperationalApproval::class)->submit(User::factory()->create(['role' => 'kasir_minum']), $this->input($this->product()));
        Livewire::actingAs(User::factory()->create(['role' => 'kasir_gym']))->test('pages::dashboard.admin.beverages.pos')
            ->call('deleteOperational', $request->id)->assertForbidden();
        $this->assertModelExists($request);
        $this->assertSame(1, $request->items()->count());
    }

    public function test_processed_requests_cannot_be_deleted(): void
    {
        $action = app(BeverageOperationalApproval::class);
        $admin = User::factory()->create(['role' => 'admin']);
        $owner = User::factory()->create(['role' => 'kasir_minum']);
        foreach (['approved', 'rejected'] as $status) {
            $request = $action->submit($owner, $this->input($this->product()));
            if ($status === 'approved') {
                $action->approve($admin, $request->id);
            } else {
                $action->reject($admin, $request->id, 'Salah input');
            }
            Livewire::actingAs($admin)->test('pages::dashboard.admin.beverages.pos')
                ->call('deleteOperational', $request->id)->assertHasErrors('approval');
            $this->assertSame($status, $request->refresh()->status);
            $this->assertSame(1, $request->items()->count());
        }
        $this->assertDatabaseCount('beverage_sales', 1);
    }

    private function product(): Beverage
    {
        return Beverage::factory()->create(['stok_sekarang' => 10, 'harga_jual' => 5000]);
    }

    private function input(Beverage $product): array
    {
        return ['selected_products' => [['beverage_id' => $product->id, 'jumlah_beli' => 2, 'harga_satuan' => 1]], 'reason' => 'Kebutuhan rapat staf', 'keterangan_bayar' => 'operasional'];
    }
}
