<?php

namespace Tests\Feature;

use App\Actions\DeleteBeverageSale;
use App\Models\Beverage;
use App\Models\BeverageSale;
use App\Models\BeverageStokSnapshot;
use App\Models\DepositBeverage;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class BeverageSaleDeletionConcurrencyTest extends TestCase
{
    use DatabaseTruncation;

    protected function beforeTruncatingDatabase(): void
    {
        RefreshDatabaseState::$migrated = false;
    }

    public function test_concurrent_deletion_and_settlement_cannot_restore_twice_or_create_orphans(): void
    {
        foreach (['delete', 'settle'] as $operation) {
            [$sale, $product, $user] = $this->fixture();
            $preview = app(DeleteBeverageSale::class)->preview($sale->id);
            $process = $this->process($operation, $sale, $user, ['SALE_TEST_FINGERPRINT' => $preview['fingerprint']]);
            DB::beginTransaction();
            try {
                BeverageSale::query()->lockForUpdate()->findOrFail($sale->id);
                $this->startBlocked($process);
                $this->assertNull(app(DeleteBeverageSale::class)->execute($sale->id, $preview['fingerprint']));
                DB::commit();
                $process->wait();
                $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
                $this->assertSame(23, $product->refresh()->stok_sekarang);
                $this->assertModelMissing($sale);
                $this->assertDatabaseMissing('beverage_sales', ['parent_beverage_sale_id' => $sale->id]);
            } finally {
                if (DB::transactionLevel() > 0) {
                    DB::rollBack();
                }
                $process->stop();
                $product->forceDelete();
                $user->delete();
            }
        }
    }

    public function test_deposit_spend_waits_and_rechecks_after_its_source_sale_is_deleted(): void
    {
        [$sale, $product, $user] = $this->fixture();
        $purchase = Beverage::factory()->create(['stok_sekarang' => 10]);
        $deposit = DepositBeverage::create(['beverage_sale_id' => $sale->id, 'nama_pelanggan' => 'Pelanggan', 'nominal' => 5000, 'sisa_nominal' => 5000, 'is_used' => false]);
        $preview = app(DeleteBeverageSale::class)->preview($sale->id);
        $process = $this->process('spend', $sale, $user, ['SALE_TEST_DEPOSIT' => (string) $deposit->id, 'SALE_TEST_PRODUCT' => (string) $purchase->id]);
        DB::beginTransaction();
        try {
            BeverageSale::query()->lockForUpdate()->findOrFail($sale->id);
            DepositBeverage::query()->lockForUpdate()->findOrFail($deposit->id);
            $this->startBlocked($process);
            $this->assertNull(app(DeleteBeverageSale::class)->execute($sale->id, $preview['fingerprint']));
            DB::commit();
            $process->wait();
            $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
            $this->assertStringContainsString('Deposit tidak ditemukan', $process->getOutput());
            $this->assertModelMissing($deposit);
            $this->assertSame(23, $product->refresh()->stok_sekarang);
            $this->assertSame(10, $purchase->refresh()->stok_sekarang);
            $this->assertDatabaseMissing('beverage_sales', ['beverage_id' => $purchase->id]);
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            $process->stop();
            $product->forceDelete();
            $purchase->forceDelete();
            $user->delete();
        }
    }

    /** @return array{BeverageSale, Beverage, User} */
    private function fixture(): array
    {
        $this->assertSame('jim_test', DB::connection()->getDatabaseName());
        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user);
        $product = Beverage::factory()->create(['stok_sekarang' => 20]);
        BeverageStokSnapshot::create(['beverage_id' => $product->id, 'tanggal' => now('Asia/Jakarta')->toDateString(), 'tipe' => 'init', 'jumlah' => 20]);
        $sale = BeverageSale::create(['beverage_id' => $product->id, 'nama_produk' => $product->nama_produk, 'nama_staff' => 'Admin', 'waktu_transaksi' => now(), 'shift' => 'pagi', 'jumlah_beli' => 3, 'harga_satuan' => 1000, 'total_harga' => 3000, 'keterangan_bayar' => 'hutang', 'is_lunas' => false]);

        return [$sale, $product, $user];
    }

    private function startBlocked(Process $process): void
    {
        $process->start();
        $process->waitUntil(fn (string $type, string $output): bool => str_contains($output, 'ready'));
        usleep(200000);
        $this->assertTrue($process->isRunning(), $process->getErrorOutput());
    }

    /** @param array<string, string> $environment */
    private function process(string $operation, BeverageSale $sale, User $user, array $environment): Process
    {
        $script = <<<'PHP'
        require 'vendor/autoload.php';
        $app = require 'bootstrap/app.php';
        $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        config(['database.default' => 'mysql', 'database.connections.mysql' => json_decode(getenv('SALE_TEST_DATABASE'), true), 'session.driver' => 'array']);
        Illuminate\Support\Facades\DB::purge('mysql');
        Illuminate\Support\Facades\Auth::loginUsingId((int) getenv('SALE_TEST_USER'));
        echo "ready\n";
        flush();
        $id = (int) getenv('SALE_TEST_ID');
        if (getenv('SALE_TEST_OPERATION') === 'delete') {
            app(App\Actions\DeleteBeverageSale::class)->execute($id, getenv('SALE_TEST_FINGERPRINT'));
        } elseif (getenv('SALE_TEST_OPERATION') === 'settle') {
            app(App\Actions\SettleBeverageDebt::class)->execute($id, 'deposit_hutang_cash');
        } else {
            $request = Illuminate\Http\Request::create('/admin/beverages/pos/process', 'POST', [
                'selected_products' => json_encode([['beverage_id' => (int) getenv('SALE_TEST_PRODUCT'), 'nama_produk' => 'Produk', 'harga_satuan' => 1000, 'jumlah_beli' => 1]]),
                'nama_staff' => 'Admin', 'keterangan_bayar' => 'deposit', 'selected_deposit_id' => getenv('SALE_TEST_DEPOSIT'),
            ]);
            $request->setUserResolver(fn () => Illuminate\Support\Facades\Auth::user());
            app(App\Http\Controllers\BeverageApiController::class)->processSale($request);
            echo Illuminate\Support\Facades\Session::get('error', 'spent');
        }
        echo "done\n";
        PHP;
        $process = new Process([PHP_BINARY, '-r', $script], base_path(), $environment + [
            'APP_ENV' => 'testing', 'SALE_TEST_DATABASE' => json_encode(config('database.connections.mysql')),
            'SALE_TEST_USER' => (string) $user->id, 'SALE_TEST_ID' => (string) $sale->id, 'SALE_TEST_OPERATION' => $operation,
        ]);
        $process->setTimeout(20);

        return $process;
    }
}
