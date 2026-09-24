<?php

namespace Tests\Feature;

use App\Actions\CancelBeverageInvoice;
use App\Actions\SaveBeverageInvoice;
use App\Models\Beverage;
use App\Models\BeverageInvoice;
use App\Models\BeverageRestock;
use App\Models\BeverageStokSnapshot;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class BeverageInvoiceConcurrencyTest extends TestCase
{
    use DatabaseTruncation;

    protected function beforeTruncatingDatabase(): void
    {
        RefreshDatabaseState::$migrated = false;
    }

    public function test_simultaneous_cancellations_only_remove_stock_once(): void
    {
        $this->assertSame('jim_test', DB::connection()->getDatabaseName());
        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user);
        $product = Beverage::factory()->create(['stok_sekarang' => 20]);
        $today = now('Asia/Jakarta')->toDateString();
        $snapshot = BeverageStokSnapshot::create(['beverage_id' => $product->id, 'tanggal' => $today, 'tipe' => 'init', 'jumlah' => 20]);
        $invoice = app(SaveBeverageInvoice::class)->execute([
            'no_faktur' => 'CONCURRENT', 'tanggal_order' => $today, 'tanggal_menerima' => $today,
            'diterima_oleh' => 'Admin', 'status' => 'lunas', 'metode_pembayaran' => 'cash',
            'items' => [['beverage_id' => $product->id, 'total_pcs' => 5, 'qty' => 1, 'harga_perdus' => 10000]],
        ]);
        $preview = app(CancelBeverageInvoice::class)->preview($invoice->id);
        $script = <<<'PHP'
        require 'vendor/autoload.php';
        $app = require 'bootstrap/app.php';
        $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        config(['database.default' => 'mysql', 'database.connections.mysql' => json_decode(getenv('INVOICE_TEST_DATABASE'), true)]);
        Illuminate\Support\Facades\DB::purge('mysql');
        Illuminate\Support\Facades\Auth::loginUsingId((int) getenv('INVOICE_TEST_USER'));
        echo "ready\n";
        flush();
        app(App\Actions\CancelBeverageInvoice::class)->execute((int) getenv('INVOICE_TEST_ID'), getenv('INVOICE_TEST_FINGERPRINT'));
        echo "done\n";
        PHP;
        $process = new Process([PHP_BINARY, '-r', $script], base_path(), [
            'APP_ENV' => 'testing',
            'INVOICE_TEST_DATABASE' => json_encode(config('database.connections.mysql')),
            'INVOICE_TEST_USER' => (string) $user->id,
            'INVOICE_TEST_ID' => (string) $invoice->id,
            'INVOICE_TEST_FINGERPRINT' => $preview['fingerprint'],
        ]);
        $process->setTimeout(20);
        DB::beginTransaction();
        try {
            BeverageInvoice::query()->lockForUpdate()->findOrFail($invoice->id);
            $process->start();
            $process->waitUntil(fn (string $type, string $output): bool => str_contains($output, 'ready'));
            usleep(200000);
            $this->assertTrue($process->isRunning(), $process->getErrorOutput());
            $this->assertNull(app(CancelBeverageInvoice::class)->execute($invoice->id, $preview['fingerprint']));
            DB::commit();
            $process->wait();
            $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
            $this->assertStringContainsString('done', $process->getOutput());
            $this->assertSame(20, $product->refresh()->stok_sekarang);
            $this->assertSame(20, $snapshot->refresh()->jumlah);
            $this->assertModelMissing($invoice);
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            $process->stop();
            BeverageRestock::query()->where('beverage_id', $product->id)->delete();
            $invoice->items()->delete();
            $invoice->delete();
            $product->forceDelete();
            $user->delete();
        }
    }
}
