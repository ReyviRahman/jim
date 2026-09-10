<?php

namespace Tests\Feature;

use App\Models\AttendanceEmployee;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class EmployeeAttendanceConcurrencyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('jim_test', DB::connection()->getDatabaseName());
        $this->artisan('migrate:fresh', ['--force' => true])->assertExitCode(0);
    }

    protected function tearDown(): void
    {
        RefreshDatabaseState::$migrated = false;
        parent::tearDown();
    }

    public function test_simultaneous_scans_create_one_attendance_without_checkout_at_the_same_second(): void
    {
        $shift = Shift::factory()->create([
            'role' => 'admin', 'start_time' => '08:00:00', 'end_time' => '16:00:00',
        ]);
        $user = User::factory()->create(['role' => 'admin', 'shift' => $shift->id]);
        $input = json_encode([
            'connection' => DB::connection()->getConfig(),
            'user_id' => $user->id,
            'received_at' => '2026-09-10 08:00:00',
        ], JSON_THROW_ON_ERROR);
        $worker = <<<'PHP'
            require getcwd().'/vendor/autoload.php';
            $input = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
            if (($input['connection']['database'] ?? null) !== 'jim_test') {
                throw new RuntimeException('Workers may only use jim_test.');
            }
            $app = require getcwd().'/bootstrap/app.php';
            $app->afterBootstrapping(Illuminate\Foundation\Bootstrap\LoadConfiguration::class, function ($app) use ($input): void {
                $connectionName = $input['connection']['name'];
                $app['config']->set('database.default', $connectionName);
                $app['config']->set('database.connections.'.$connectionName, $input['connection']);
                $app['config']->set('app.timezone', 'Asia/Jakarta');
            });
            $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
            if (Illuminate\Support\Facades\DB::connection()->getDatabaseName() !== 'jim_test') {
                throw new RuntimeException('Unexpected worker database.');
            }
            $user = App\Models\User::findOrFail($input['user_id']);
            echo "ready\n";
            flush();
            $attendance = $app->make(App\EmployeeAttendanceService::class)->record(
                $user,
                Illuminate\Support\Carbon::parse($input['received_at'], 'Asia/Jakarta'),
            );
            echo 'attendance:'.$attendance->id."\n";
            PHP;

        $processes = [];
        DB::beginTransaction();
        try {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            for ($index = 0; $index < 2; $index++) {
                $process = new Process([PHP_BINARY, '-r', $worker], base_path(), ['APP_ENV' => 'testing']);
                $process->setInput($input);
                $process->setTimeout(30);
                $processes[] = $process;
                $process->start();
            }
            foreach ($processes as $process) {
                $deadline = microtime(true) + 15;
                while (! str_contains($process->getOutput(), "ready\n") && $process->isRunning() && microtime(true) < $deadline) {
                    usleep(10_000);
                }
                $this->assertStringContainsString("ready\n", $process->getOutput(), $process->getErrorOutput());
                $this->assertTrue($process->isRunning());
            }
            DB::commit();

            foreach ($processes as $process) {
                $this->assertSame(0, $process->wait(), $process->getErrorOutput());
            }
            $this->assertDatabaseCount('attendance_employee', 1);
            $attendance = AttendanceEmployee::query()->sole();
            foreach ($processes as $process) {
                $this->assertStringContainsString('attendance:'.$attendance->id."\n", $process->getOutput());
            }
            $this->assertSame($user->id, $attendance->user_id);
            $this->assertSame('2026-09-10 08:00:00', $attendance->check_in_time->format('Y-m-d H:i:s'));
            $this->assertNull($attendance->check_out_time);
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop(0);
                }
            }
        }
    }
}
