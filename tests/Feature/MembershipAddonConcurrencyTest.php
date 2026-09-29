<?php

namespace Tests\Feature;

use App\Actions\MembershipAddonApproval;
use App\Models\Membership;
use App\Models\MembershipAddon;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class MembershipAddonConcurrencyTest extends TestCase
{
    public function test_concurrent_decisions_preserve_first_admin_and_do_not_duplicate_addon(): void
    {
        $this->assertSame('jim_test', DB::connection()->getDatabaseName());
        foreach (['approve', 'reject'] as $operation) {
            $firstAdmin = User::factory()->create(['role' => 'admin']);
            $secondAdmin = User::factory()->create(['role' => 'admin']);
            $addon = MembershipAddon::factory()->create(['requested_by' => $firstAdmin->id]);
            $membership = $addon->membership;
            $member = $membership->user;
            $process = $this->decisionProcess($operation, $addon, $secondAdmin);
            DB::beginTransaction();
            try {
                Membership::whereKey($membership->id)->lockForUpdate()->firstOrFail();
                $process->start();
                $process->waitUntil(fn (string $type, string $output): bool => str_contains($output, 'ready'));
                usleep(200000);
                $this->assertTrue($process->isRunning(), $process->getErrorOutput());
                app(MembershipAddonApproval::class)->approve($firstAdmin, $addon->id);
                DB::commit();
                $process->wait();
                $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
                $this->assertStringContainsString($operation === 'approve' ? 'approved' : 'decision-rejected', $process->getOutput());
                $this->assertSame('approved', $addon->refresh()->approval_status);
                $this->assertSame($firstAdmin->id, $addon->decided_by);
                $this->assertSame(1, MembershipAddon::where('membership_id', $membership->id)->count());
            } finally {
                while (DB::transactionLevel() > 0) {
                    DB::rollBack();
                }
                $process->stop();
                $membership->delete();
                foreach ([$firstAdmin, $secondAdmin, $member] as $user) {
                    $user->forceDelete();
                }
            }
        }
    }

    private function decisionProcess(string $operation, MembershipAddon $addon, User $admin): Process
    {
        $script = <<<'PHP'
        require 'vendor/autoload.php';
        $app = require 'bootstrap/app.php';
        $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        config(['database.default' => 'mysql', 'database.connections.mysql' => json_decode(getenv('ADDON_TEST_DATABASE'), true)]);
        Illuminate\Support\Facades\DB::purge('mysql');
        if (Illuminate\Support\Facades\DB::connection()->getDatabaseName() !== 'jim_test') { throw new RuntimeException('Test database required'); }
        $admin = App\Models\User::findOrFail((int) getenv('ADDON_TEST_ADMIN'));
        while (ob_get_level() > 0) { ob_end_flush(); }
        echo "ready\n"; flush();
        try {
            $action = app(App\Actions\MembershipAddonApproval::class);
            if (getenv('ADDON_TEST_OPERATION') === 'approve') {
                $action->approve($admin, (int) getenv('ADDON_TEST_ID'));
                echo 'approved';
            } else {
                $action->reject($admin, (int) getenv('ADDON_TEST_ID'), 'Concurrent rejection');
                echo 'rejected';
            }
        } catch (Illuminate\Validation\ValidationException $exception) { echo 'decision-rejected'; }
        PHP;

        return new Process([PHP_BINARY, '-r', $script], base_path(), [
            'APP_ENV' => 'testing', 'ADDON_TEST_DATABASE' => json_encode(config('database.connections.mysql')),
            'ADDON_TEST_ADMIN' => (string) $admin->id, 'ADDON_TEST_ID' => (string) $addon->id, 'ADDON_TEST_OPERATION' => $operation,
        ], timeout: 20);
    }
}
