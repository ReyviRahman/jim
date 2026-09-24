<?php

namespace Tests\Feature;

use App\Actions\CreateMemberPtBooking;
use App\Models\Membership;
use App\Models\PtBooking;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class PtStudioConcurrencyTest extends TestCase
{
    use DatabaseTruncation;

    protected function beforeTruncatingDatabase(): void
    {
        $this->assertSame('jim_test', DB::connection()->getDatabaseName());
    }

    protected function tearDown(): void
    {
        try {
            if (DB::connection()->getDatabaseName() === 'jim_test') {
                $this->truncateTablesForAllConnections();
            }
        } finally {
            parent::tearDown();
        }
    }

    public function test_concurrent_requests_cannot_both_take_the_last_private_place(): void
    {
        $memberships = collect(range(1, 3))->map(fn (): Membership => Membership::create([
            'user_id' => User::factory()->create(['role' => 'member'])->id,
            'pt_id' => User::factory()->create(['role' => 'pt'])->id,
            'type' => 'pt', 'base_price' => 300000, 'normal_price' => 300000,
            'price_paid' => 300000, 'total_paid' => 300000, 'payment_status' => 'paid',
            'start_date' => today()->toDateString(), 'pt_end_date' => today()->addMonth()->toDateString(),
            'status' => 'active', 'is_active' => true, 'total_sessions' => 10, 'remaining_sessions' => 10,
        ]));
        $date = today()->addDay()->toDateString();
        $first = $memberships->first();
        app(CreateMemberPtBooking::class)->execute($first->user, $first->id, $date, '09:00', 'private_studio');
        $lock = 'pt-studio:'.sha1(DB::connection()->getDatabaseName());
        DB::selectOne('SELECT GET_LOCK(?, 10) AS acquired', [$lock]);
        $workers = [];
        $readyFiles = [];
        try {
            foreach ($memberships->skip(1) as $membership) {
                $readyFile = tempnam(sys_get_temp_dir(), 'pt-studio-ready-');
                $readyFiles[] = $readyFile;
                $payload = base64_encode(json_encode([
                    'readyFile' => $readyFile,
                    'connection' => config('database.connections.mysql'),
                    'membership' => $membership->id,
                    'date' => $date,
                ], JSON_THROW_ON_ERROR));
                $script = <<<'PHP'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$data = json_decode(base64_decode($argv[1]), true);
config(['database.default' => 'mysql', 'database.connections.mysql' => $data['connection']]);
Illuminate\Support\Facades\DB::purge('mysql');
$membership = App\Models\Membership::findOrFail($data['membership']);
file_put_contents($data['readyFile'], 'ready');
try {
    app(App\Actions\CreateMemberPtBooking::class)->execute($membership->user, $membership->id, $data['date'], '09:00', 'private_studio');
    echo "BOOKED\n";
} catch (Illuminate\Validation\ValidationException $exception) {
    echo "REJECTED:".$exception->getMessage()."\n";
}
PHP;
                $worker = new Process([PHP_BINARY, '-r', $script, $payload], base_path(), ['APP_ENV' => 'testing']);
                $worker->setTimeout(20);
                $worker->start();
                $workers[] = $worker;
            }
            $deadline = microtime(true) + 5;
            do {
                clearstatcache();
                $ready = count(array_filter($readyFiles, fn (string $file): bool => filesize($file) > 0));
                if ($ready === 2) {
                    break;
                }
                usleep(20000);
            } while (microtime(true) < $deadline);
            $this->assertSame(2, $ready, 'Both workers must be ready before releasing the lock.');
        } finally {
            DB::selectOne('SELECT RELEASE_LOCK(?) AS released', [$lock]);
            foreach ($readyFiles as $file) {
                unlink($file);
            }
        }
        $outputs = [];
        foreach ($workers as $worker) {
            $worker->wait();
            $this->assertTrue($worker->isSuccessful(), $worker->getErrorOutput());
            $outputs[] = $worker->getOutput();
        }
        $this->assertCount(1, array_filter($outputs, fn (string $output): bool => str_contains($output, 'BOOKED')), implode('; ', $outputs));
        $this->assertCount(1, array_filter($outputs, fn (string $output): bool => str_contains($output, 'REJECTED:Private Studio penuh')));
        $this->assertSame(2, PtBooking::where('studio_type', 'private_studio')->count());
    }
}
