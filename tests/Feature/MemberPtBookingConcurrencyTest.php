<?php

namespace Tests\Feature;

use App\Models\Membership;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class MemberPtBookingConcurrencyTest extends TestCase
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

    public function test_two_members_racing_for_the_same_coach_only_create_one_booking(): void
    {
        $this->assertSame('mysql', DB::connection()->getDriverName());
        $this->assertSame('jim_test', DB::connection()->getDatabaseName());
        $coach = User::factory()->create(['role' => 'pt']);
        $memberships = collect(range(1, 2))->map(fn (): Membership => Membership::create([
            'user_id' => User::factory()->create(['role' => 'member'])->id,
            'pt_id' => $coach->id, 'type' => 'pt', 'status' => 'active', 'is_active' => true,
            'base_price' => 300000, 'price_paid' => 300000, 'total_paid' => 300000,
            'payment_status' => 'paid', 'start_date' => today()->toDateString(),
            'pt_end_date' => today()->addMonth()->toDateString(),
            'remaining_sessions' => 10, 'total_sessions' => 10,
        ]));

        $workerCode = <<<'PHP'
        require 'vendor/autoload.php';
        $app = require 'bootstrap/app.php';
        $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        config(['database.default' => 'mysql', 'database.connections.mysql' => json_decode(getenv('PT_TEST_DATABASE'), true)]);
        \Illuminate\Support\Facades\DB::purge('mysql');
        if (\Illuminate\Support\Facades\DB::connection()->getDatabaseName() !== 'jim_test') {
            throw new \RuntimeException('Concurrency test must only use jim_test.');
        }
        \Illuminate\Support\Facades\DB::listen(function ($query): void {
            if (str_contains($query->sql, 'from `memberships`') && str_contains($query->sql, 'for update')) {
                echo "membership-locked\n";
                flush();
            }
        });
        try {
            $app->make(\App\Actions\CreateMemberPtBooking::class)->execute(
                \App\Models\User::findOrFail((int) $argv[1]), (int) $argv[2], $argv[3], '07:00',
            );
            echo "created\n";
        } catch (\Illuminate\Validation\ValidationException $exception) {
            echo "rejected\n";
        }
        PHP;
        $workers = $memberships->map(fn (Membership $membership): Process => new Process(
            [PHP_BINARY, '-r', $workerCode, (string) $membership->user_id, (string) $membership->id, today()->addDay()->toDateString()],
            base_path(),
            ['APP_ENV' => 'testing', 'PT_TEST_DATABASE' => json_encode(config('database.connections.mysql'), JSON_THROW_ON_ERROR)],
            timeout: 20,
        ));

        DB::beginTransaction();
        User::query()->whereKey($coach->id)->lockForUpdate()->firstOrFail();

        try {
            foreach ($workers as $worker) {
                $worker->start();
            }

            $deadline = microtime(true) + 12;
            do {
                $bothInsideTransaction = $workers->every(fn (Process $worker): bool => str_contains($worker->getOutput(), 'membership-locked'));
                if (! $bothInsideTransaction) {
                    usleep(20000);
                }
            } while (! $bothInsideTransaction && microtime(true) < $deadline);

            $this->assertTrue($bothInsideTransaction, 'Both requests must enter their transactions before the coach lock is released.');
            $this->assertTrue($workers->every(fn (Process $worker): bool => $worker->isRunning()));
            DB::commit();

            foreach ($workers as $worker) {
                $worker->wait();
                $this->assertTrue($worker->isSuccessful(), $worker->getErrorOutput());
            }

            $outcomes = $workers->map(fn (Process $worker): string => str($worker->getOutput())->trim()->afterLast("\n")->toString())->sort()->values()->all();
            $this->assertSame(['created', 'rejected'], $outcomes);
            $this->assertDatabaseCount('pt_bookings', 1);
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            foreach ($workers as $worker) {
                $worker->stop();
            }
        }
    }
}
