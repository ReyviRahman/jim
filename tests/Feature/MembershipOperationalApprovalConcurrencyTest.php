<?php

namespace Tests\Feature;

use App\Actions\MembershipOperationalApproval;
use App\Models\GymPackage;
use App\Models\Membership;
use App\Models\MembershipOperationalRequest;
use App\Models\MembershipTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class MembershipOperationalApprovalConcurrencyTest extends TestCase
{
    public function test_concurrent_approval_and_deletion_cannot_duplicate_or_remove_approved_membership(): void
    {
        $this->assertSame('mysql', DB::connection()->getDriverName());
        $this->assertSame('jim_test', DB::connection()->getDatabaseName());
        foreach (['approve', 'delete'] as $operation) {
            [$request, $package, $users] = $this->fixture();
            $process = $this->process($operation, $request, $users[2]);
            DB::beginTransaction();
            try {
                MembershipOperationalRequest::lockForUpdate()->findOrFail($request->id);
                $process->start();
                $process->waitUntil(fn (string $type, string $output): bool => str_contains($output, 'ready'));
                usleep(200000);
                $this->assertTrue($process->isRunning(), $process->getErrorOutput());
                app(MembershipOperationalApproval::class)->approve($users[1], $request->id);
                DB::commit();
                $process->wait();
                $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
                $this->assertStringContainsString($operation === 'approve' ? 'approved' : 'decision-rejected', $process->getOutput());
                $this->assertSame('approved', $request->refresh()->status);
                $this->assertSame($users[1]->id, $request->decided_by);
                $this->assertSame(1, Membership::where('operational_request_id', $request->id)->count());
                $this->assertSame(1, MembershipTransaction::where('operational_request_id', $request->id)->count());
                $this->assertSame(1, $request->membership->waivers()->count());
                Storage::disk('local')->assertExists($request->documents[0]['signature_path']);
            } finally {
                while (DB::transactionLevel() > 0) {
                    DB::rollBack();
                }
                $process->stop();
                foreach (Membership::where('operational_request_id', $request->id)->get() as $membership) {
                    $membership->waivers()->delete();
                    $membership->delete();
                }
                foreach ($request->documents as $document) {
                    Storage::disk('local')->delete($document['signature_path']);
                }
                $request->delete();
                $package->delete();
                foreach ($users as $user) {
                    $user->delete();
                }
            }
        }
    }

    private function fixture(): array
    {
        $cashier = User::factory()->create(['role' => 'kasir_gym']);
        $firstAdmin = User::factory()->create(['role' => 'admin']);
        $secondAdmin = User::factory()->create(['role' => 'admin']);
        $member = User::factory()->create(['role' => 'member', 'photo' => 'existing.jpg']);
        $package = GymPackage::create(['name' => 'Concurrency Gym', 'type' => 'gym', 'category' => 'single', 'price' => 100000, 'discount' => 0, 'duration_days' => 30, 'is_active' => true]);
        $image = imagecreatetruecolor(400, 160);
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
        imageline($image, 25, 100, 360, 50, imagecolorallocate($image, 0, 0, 0));
        ob_start();
        imagepng($image);
        $bytes = ob_get_clean();
        imagedestroy($image);
        $request = app(MembershipOperationalApproval::class)->submit($cashier, [
            'submission_token' => (string) Str::uuid(), 'user_ids' => [$member->id], 'registration_type' => 'membership',
            'gym_package_id' => $package->id, 'admin_id' => $cashier->id, 'is_active' => false,
            'payment_date' => today()->toDateString(), 'transaction_type' => 'Baru', 'package_name' => 'Gym', 'notes' => 'Concurrency',
            'reason' => 'Concurrency', 'pt_trial_interest' => 'no', 'payment_type' => 'paid', 'is_split_payment' => false,
        ], [$member->id => ['accepted' => true, 'signature' => 'data:image/png;base64,'.base64_encode($bytes)]], []);

        return [$request, $package, [$cashier, $firstAdmin, $secondAdmin, $member]];
    }

    private function process(string $operation, MembershipOperationalRequest $request, User $admin): Process
    {
        $script = <<<'PHP'
        require 'vendor/autoload.php';
        $app = require 'bootstrap/app.php';
        $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        config(['database.default' => 'mysql', 'database.connections.mysql' => json_decode(getenv('MEMBERSHIP_TEST_DATABASE'), true), 'session.driver' => 'array']);
        Illuminate\Support\Facades\DB::purge('mysql');
        if (Illuminate\Support\Facades\DB::connection()->getDatabaseName() !== 'jim_test') { throw new RuntimeException('Test database required'); }
        $admin = App\Models\User::findOrFail((int) getenv('MEMBERSHIP_TEST_ADMIN'));
        while (ob_get_level() > 0) { ob_end_flush(); }
        echo "ready\n"; flush();
        try {
            $action = app(App\Actions\MembershipOperationalApproval::class);
            if (getenv('MEMBERSHIP_TEST_OPERATION') === 'approve') {
                echo $action->approve($admin, (int) getenv('MEMBERSHIP_TEST_REQUEST'))->status;
            } else {
                $action->deletePending($admin, (int) getenv('MEMBERSHIP_TEST_REQUEST'));
                echo 'deleted';
            }
        } catch (Illuminate\Validation\ValidationException $exception) { echo 'decision-rejected'; }
        PHP;
        $process = new Process([PHP_BINARY, '-r', $script], base_path(), [
            'APP_ENV' => 'testing', 'MEMBERSHIP_TEST_DATABASE' => json_encode(config('database.connections.mysql')),
            'MEMBERSHIP_TEST_ADMIN' => (string) $admin->id, 'MEMBERSHIP_TEST_REQUEST' => (string) $request->id, 'MEMBERSHIP_TEST_OPERATION' => $operation,
        ]);
        $process->setTimeout(20);

        return $process;
    }
}
