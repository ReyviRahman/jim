<?php

namespace Tests\Feature;

use App\Models\Membership;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class MembershipRenewalConcurrencyTest extends TestCase
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

    public function test_concurrent_renewals_form_a_chain_instead_of_overwriting_the_same_predecessor(): void
    {
        $member = User::factory()->create(['role' => 'member']);
        $old = Membership::create([
            'user_id' => $member->id, 'type' => 'membership', 'base_price' => 300000,
            'price_paid' => 300000, 'total_paid' => 300000, 'payment_status' => 'paid',
            'status' => 'active', 'is_active' => true,
        ]);
        $script = <<<'PHP'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(['database.default' => 'mysql', 'database.connections.mysql' => json_decode(getenv('RENEWAL_TEST_DATABASE'), true)]);
Illuminate\Support\Facades\DB::purge('mysql');
if (Illuminate\Support\Facades\DB::connection()->getDatabaseName() !== 'jim_test') {
    throw new RuntimeException('Concurrency test must only use jim_test.');
}
Illuminate\Support\Facades\DB::transaction(function () use ($argv): void {
    echo "ready\n";
    flush();
    $action = app(App\Actions\LinkMembershipRenewal::class);
    $ids = [(int) $argv[1]];
    $action->lockMembers($ids);
    usleep(150000);
    $renewal = App\Models\Membership::create([
        'user_id' => $ids[0], 'type' => 'membership', 'base_price' => 300000,
        'price_paid' => 300000, 'total_paid' => 300000, 'payment_status' => 'paid',
        'status' => 'active', 'is_active' => true, 'is_renewal' => true,
    ]);
    $renewal->members()->attach($ids);
    $action->execute($renewal, $ids);
});
PHP;
        $workers = collect(range(1, 2))->map(fn (): Process => new Process(
            [PHP_BINARY, '-r', $script, (string) $member->id],
            base_path(),
            ['APP_ENV' => 'testing', 'RENEWAL_TEST_DATABASE' => json_encode(config('database.connections.mysql'), JSON_THROW_ON_ERROR)],
            timeout: 20,
        ));

        DB::beginTransaction();
        User::whereKey($member->id)->lockForUpdate()->firstOrFail();
        try {
            foreach ($workers as $worker) {
                $worker->start();
            }
            $deadline = microtime(true) + 12;
            do {
                $ready = $workers->every(fn (Process $worker): bool => str_contains($worker->getOutput(), 'ready'));
                if (! $ready) {
                    usleep(20000);
                }
            } while (! $ready && microtime(true) < $deadline);
            $this->assertTrue($ready, 'Both purchases must start before releasing the member lock.');
            $this->assertTrue($workers->every(fn (Process $worker): bool => $worker->isRunning()));
            DB::commit();

            foreach ($workers as $worker) {
                $worker->wait();
                $this->assertTrue($worker->isSuccessful(), $worker->getErrorOutput());
            }

            $renewals = Membership::where('is_renewal', true)->orderBy('id')->get();
            $this->assertCount(2, $renewals);
            $this->assertSame($renewals[0]->id, $old->refresh()->renewal_from);
            $this->assertSame($renewals[1]->id, $renewals[0]->renewal_from);
            $this->assertNull($renewals[1]->renewal_from);
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
