<?php

namespace Tests\Feature;

use App\EmployeeAttendanceStatus;
use App\Models\AttendanceEmployee;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use RuntimeException;
use Tests\TestCase;

class MarkMissingEmployeeAttendanceSickTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('jim_test', DB::connection()->getDatabaseName());
        $this->travelTo(Carbon::parse('2026-09-21 23:57:00', 'Asia/Jakarta'));
    }

    public function test_creates_sick_attendance_without_shift_and_is_safe_to_repeat(): void
    {
        $employee = $this->employee();
        $this->artisan('employees:mark-missing-attendance-sick')
            ->expectsOutput('Tanggal 2026-09-21: dibuat 1, diperbarui 0, dilewati 0.')
            ->assertSuccessful();
        $row = AttendanceEmployee::query()->sole();
        $this->assertSame($employee->id, $row->user_id);
        $this->assertSame(EmployeeAttendanceStatus::Sakit, $row->status);
        $this->assertSame('2026-09-21', $row->attendance_date->toDateString());
        foreach (['check_in_time', 'check_out_time', 'shift_code', 'shift_name', 'shift_role', 'shift_start_time', 'shift_end_time', 'scheduled_start_at', 'scheduled_end_at', 'checkout_deadline_at', 'nama_di_alat', 'device_event_id'] as $field) {
            $this->assertNull($row->$field);
        }
        $before = $row->fresh()->getRawOriginal();
        $this->artisan('employees:mark-missing-attendance-sick')
            ->expectsOutput('Tanggal 2026-09-21: dibuat 0, diperbarui 0, dilewati 1.')
            ->assertSuccessful();
        $this->assertSame($before, $row->fresh()->getRawOriginal());
        $this->assertDatabaseCount('attendance_employee', 1);
    }

    public function test_updates_only_status_of_present_schedule_without_check_in(): void
    {
        $row = AttendanceEmployee::factory()->create([
            'user_id' => $this->employee()->id, 'check_in_time' => null, 'notes' => 'Catatan admin',
        ]);
        $before = $row->fresh()->getRawOriginal();
        $this->artisan('employees:mark-missing-attendance-sick')
            ->expectsOutput('Tanggal 2026-09-21: dibuat 0, diperbarui 1, dilewati 0.')
            ->assertSuccessful();
        $row->refresh();
        $this->assertSame(EmployeeAttendanceStatus::Sakit, $row->status);
        $this->assertSame(collect($before)->except(['status', 'updated_at'])->all(), collect($row->getRawOriginal())->except(['status', 'updated_at'])->all());
        $this->assertDatabaseCount('attendance_employee', 1);
    }

    public function test_preserves_checked_in_attendance_and_existing_absence_statuses(): void
    {
        $rows = collect([AttendanceEmployee::factory()->create(['user_id' => $this->employee()->id])]);
        foreach ([EmployeeAttendanceStatus::Izin, EmployeeAttendanceStatus::Off, EmployeeAttendanceStatus::Sakit] as $status) {
            $rows->push(AttendanceEmployee::factory()->create([
                'user_id' => $this->employee()->id, 'status' => $status, 'check_in_time' => null,
            ]));
        }
        $before = $rows->map(fn (AttendanceEmployee $row): array => $row->fresh()->getRawOriginal())->all();
        $this->artisan('employees:mark-missing-attendance-sick')
            ->expectsOutput('Tanggal 2026-09-21: dibuat 0, diperbarui 0, dilewati 4.')
            ->assertSuccessful();
        $this->assertSame($before, $rows->map(fn (AttendanceEmployee $row): array => $row->fresh()->getRawOriginal())->all());
    }

    public function test_uses_the_employee_table_scope(): void
    {
        foreach (['pt', 'kasir_gym', 'kasir_minum', 'sales', 'cleaning_service'] as $role) {
            $this->employee(['role' => $role]);
        }
        foreach ([['role' => 'admin'], ['role' => 'member'], ['role' => 'head_coach'], ['is_active' => false], ['role' => 'sales', 'is_sales_online' => true], ['email' => User::HEAD_COACH_EMAIL]] as $attributes) {
            $this->employee($attributes);
        }
        $expected = User::query()->forEmployeeAttendance()->orderBy('id')->pluck('id')->all();
        $this->artisan('employees:mark-missing-attendance-sick')->assertSuccessful();
        $this->assertCount(5, $expected);
        $this->assertSame($expected, AttendanceEmployee::query()->orderBy('user_id')->pluck('user_id')->all());
    }

    public function test_does_not_modify_other_dates(): void
    {
        $employee = $this->employee();
        $rows = collect(['2026-09-20', '2026-09-22'])->map(fn (string $date): AttendanceEmployee => AttendanceEmployee::factory()->create([
            'user_id' => $employee->id, 'attendance_date' => $date, 'check_in_time' => null,
        ]));
        $before = $rows->map(fn (AttendanceEmployee $row): array => $row->fresh()->getRawOriginal())->all();
        $this->artisan('employees:mark-missing-attendance-sick')->assertSuccessful();
        $this->assertSame($before, $rows->map(fn (AttendanceEmployee $row): array => $row->fresh()->getRawOriginal())->all());
        $this->assertDatabaseCount('attendance_employee', 3);
    }

    public function test_run_keeps_its_start_date_when_processing_crosses_midnight(): void
    {
        $this->employee();
        $this->employee();
        Event::listen('eloquent.created: '.AttendanceEmployee::class, function (): void {
            $this->travelTo(Carbon::parse('2026-09-22 00:01:00', 'Asia/Jakarta'));
        });
        $this->artisan('employees:mark-missing-attendance-sick')
            ->expectsOutput('Tanggal 2026-09-21: dibuat 2, diperbarui 0, dilewati 0.')
            ->assertSuccessful();
        $this->assertSame(['2026-09-21'], AttendanceEmployee::query()->distinct()->pluck('attendance_date')->map->toDateString()->all());
    }

    public function test_database_failure_rolls_back_and_returns_failure(): void
    {
        $this->employee();
        Event::listen('eloquent.creating: '.AttendanceEmployee::class, function (): void {
            throw new RuntimeException('Simulasi gagal simpan');
        });
        $this->artisan('employees:mark-missing-attendance-sick')
            ->expectsOutput('Pemeriksaan absensi gagal: Simulasi gagal simpan')
            ->assertFailed();
        $this->assertDatabaseCount('attendance_employee', 0);
    }

    public function test_schedule_runs_at_2357_jakarta_without_overlapping(): void
    {
        $event = collect(app(Schedule::class)->events())->sole(fn ($event): bool => str_contains($event->command ?? '', 'employees:mark-missing-attendance-sick'));
        $this->assertSame('57 23 * * *', $event->expression);
        $this->assertSame('Asia/Jakarta', $event->timezone);
        $this->assertTrue($event->withoutOverlapping);
        $this->assertSame(storage_path('logs/employee-attendance-sick.log'), $event->output);
        $this->assertTrue($event->isDue($this->app));
        $this->travelTo(Carbon::parse('2026-09-21 23:56:00', 'Asia/Jakarta'));
        $this->assertFalse($event->isDue($this->app));
    }

    /** @param array<string, mixed> $attributes */
    private function employee(array $attributes = []): User
    {
        return User::factory()->create(['role' => 'pt', 'is_active' => true, 'is_sales_online' => false, 'shift' => null, ...$attributes]);
    }
}
