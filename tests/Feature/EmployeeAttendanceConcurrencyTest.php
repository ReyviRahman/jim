<?php

namespace Tests\Feature;

use App\EmployeeAttendanceEditor;
use App\EmployeeAttendanceService;
use App\EmployeeAttendanceStatus;
use App\Models\AttendanceEmployee;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Carbon;
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
        $this->assertSimultaneousScans(false);
    }

    public function test_simultaneous_scans_fill_scheduled_attendance_without_checkout(): void
    {
        $this->assertSimultaneousScans(true);
    }

    public function test_bulk_creation_racing_with_scan_never_overwrites_or_partially_saves(): void
    {
        $this->assertSimultaneousScans(false, true);
    }

    public function test_sick_command_waits_for_scan_and_preserves_committed_check_in(): void
    {
        foreach ([false, true] as $scheduled) {
            $shift = Shift::factory()->create(['role' => 'pt', 'start_time' => '22:00:00', 'end_time' => '06:00:00']);
            $user = User::factory()->create(['role' => 'pt', 'is_active' => true, 'shift' => $shift->id]);
            if ($scheduled) {
                AttendanceEmployee::factory()->create(['user_id' => $user->id, 'attendance_date' => '2026-09-21', 'check_in_time' => null]);
            }
            $worker = <<<'PHP'
                require getcwd().'/vendor/autoload.php';
                $input = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
                if ($input['connection']['database'] !== 'jim_test') {
                    throw new RuntimeException('Workers may only use jim_test.');
                }
                $app = require getcwd().'/bootstrap/app.php';
                $app->afterBootstrapping(Illuminate\Foundation\Bootstrap\LoadConfiguration::class, function ($app) use ($input): void {
                    $name = $input['connection']['name'];
                    $app['config']->set('database.default', $name);
                    $app['config']->set('database.connections.'.$name, $input['connection']);
                });
                $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
                Illuminate\Support\Carbon::setTestNow(Illuminate\Support\Carbon::parse('2026-09-21 23:57:00', 'Asia/Jakarta'));
                Illuminate\Support\Facades\DB::listen(function ($query): void {
                    if (str_contains($query->sql, 'from `users`') && ! str_contains($query->sql, 'for update')) {
                        echo "selected\n";
                        flush();
                    }
                });
                $code = Illuminate\Support\Facades\Artisan::call('employees:mark-missing-attendance-sick');
                echo Illuminate\Support\Facades\Artisan::output();
                exit($code);
                PHP;
            $process = new Process([PHP_BINARY, '-r', $worker], base_path(), ['APP_ENV' => 'testing']);
            $process->setInput(json_encode(['connection' => DB::connection()->getConfig()], JSON_THROW_ON_ERROR));
            $process->setTimeout(30);
            DB::beginTransaction();
            try {
                User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
                $process->start();
                $deadline = microtime(true) + 15;
                while (! str_contains($process->getOutput(), "selected\n") && $process->isRunning() && microtime(true) < $deadline) {
                    usleep(10_000);
                }
                $this->assertStringContainsString("selected\n", $process->getOutput(), $process->getErrorOutput());
                $this->assertTrue($process->isRunning());
                $attendance = app(EmployeeAttendanceService::class)->record($user, Carbon::parse('2026-09-21 23:56:59', 'Asia/Jakarta'));
                DB::commit();
                $this->assertSame(0, $process->wait(), $process->getErrorOutput());
                $attendance->refresh();
                $this->assertSame(EmployeeAttendanceStatus::Hadir, $attendance->status);
                $this->assertSame('23:56:59', $attendance->check_in_time->format('H:i:s'));
                $this->assertSame(1, $user->employeeAttendances()->count());
            } finally {
                if (DB::transactionLevel() > 0) {
                    DB::rollBack();
                }
                if ($process->isRunning()) {
                    $process->stop(0);
                }
            }
        }
    }

    private function assertSimultaneousScans(bool $scheduled, bool $bulk = false): void
    {
        $shift = Shift::factory()->create([
            'role' => $bulk ? 'pt' : 'admin', 'start_time' => '08:00:00', 'end_time' => '16:00:00',
        ]);
        $user = User::factory()->create(['role' => $shift->role, 'shift' => $shift->id]);
        $actor = $bulk ? User::factory()->create(['role' => 'admin']) : $user;
        if ($scheduled) {
            app(EmployeeAttendanceEditor::class)->save($user, $user->id, '2026-09-10', null, ['status' => 'hadir', 'shift' => (string) $shift->id]);
        }
        $input = [
            'connection' => DB::connection()->getConfig(),
            'user_id' => $user->id,
            'actor_id' => $actor->id,
            'received_at' => '2026-09-10 06:00:00',
        ];
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
            if ($input['bulk']) {
                try {
                    $count = $app->make(App\EmployeeAttendanceEditor::class)->saveBulk(
                        App\Models\User::findOrFail($input['actor_id']),
                        [['employeeId' => $user->id, 'date' => '2026-09-09'], ['employeeId' => $user->id, 'date' => '2026-09-10']],
                        '2026-09', '', ['status' => 'hadir', 'shift' => (string) $user->shift],
                    );
                    echo 'bulk:'.$count."\n";
                } catch (Illuminate\Validation\ValidationException) {
                    echo "conflict\n";
                }
                exit(0);
            }
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
                $process->setInput(json_encode($input + ['bulk' => $bulk && $index === 1], JSON_THROW_ON_ERROR));
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
            $bulkSaved = $bulk && str_contains($processes[1]->getOutput(), 'bulk:2');
            $this->assertDatabaseCount('attendance_employee', $bulkSaved ? 2 : 1);
            $attendance = AttendanceEmployee::query()->where('attendance_date', '2026-09-10')->sole();
            foreach ($bulk ? [$processes[0]] : $processes as $process) {
                $this->assertStringContainsString('attendance:'.$attendance->id."\n", $process->getOutput());
            }
            if ($bulk && ! $bulkSaved) {
                $this->assertStringContainsString('conflict', $processes[1]->getOutput());
                $this->assertDatabaseMissing('attendance_employee', ['attendance_date' => '2026-09-09']);
            }
            $this->assertSame($user->id, $attendance->user_id);
            $this->assertSame('2026-09-10 06:00:00', $attendance->check_in_time->format('Y-m-d H:i:s'));
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
