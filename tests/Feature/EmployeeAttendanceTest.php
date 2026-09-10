<?php

namespace Tests\Feature;

use App\EmployeeAttendanceService;
use App\Models\AttendanceEmployee;
use App\Models\DeviceEvent;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class EmployeeAttendanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_inclusive_check_in_window_and_snapshot(): void
    {
        foreach (['08:00:00', '12:00:00', '16:00:00'] as $time) {
            $row = $this->record($this->employee(), '2026-09-10 '.$time);
            $this->assertSame('2026-09-10', $row->attendance_date->toDateString());
            $this->assertSame('2026-09-10 '.$time, $row->check_in_time->toDateTimeString());
            $this->assertNull($row->check_out_time);
            $this->assertSame('P', $row->shift_code);
            $this->assertSame('Pagi', $row->shift_name);
            $this->assertSame('admin', $row->shift_role);
            $this->assertSame('2026-09-10 08:00:00', $row->scheduled_start_at->toDateTimeString());
            $this->assertSame('2026-09-10 16:00:00', $row->scheduled_end_at->toDateTimeString());
            $this->assertSame('2026-09-11 08:00:00', $row->checkout_deadline_at->toDateTimeString());
        }
        $this->assertDatabaseCount('attendance_employee', 3);
        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_outside_window_and_invalid_assignments_are_rejected(): void
    {
        foreach (['07:00:00', '07:59:59', '16:00:01', '23:00:00'] as $time) {
            $this->assertRejected($this->employee(), '2026-09-10 '.$time);
        }
        $missing = User::factory()->create(['role' => 'admin', 'shift' => null]);
        $mismatch = $this->employee();
        $mismatch->update(['role' => 'sales']);
        foreach ([$missing, $mismatch, $this->employee(['end_time' => '08:00:00'])] as $user) {
            $this->assertRejected($user, '2026-09-10 08:00:00');
        }
        $this->assertDatabaseCount('attendance_employee', 0);
    }

    public function test_checkout_after_shift_uses_latest_time_and_never_moves_backward(): void
    {
        $user = $this->employee();
        $row = $this->record($user, '2026-09-10 08:00:00');
        foreach (['12:00:00', '18:00:00', '17:00:00'] as $time) {
            $this->record($user, '2026-09-10 '.$time);
        }
        $this->assertDatabaseCount('attendance_employee', 1);
        $this->assertSame('2026-09-10 08:00:00', $row->fresh()->check_in_time->toDateTimeString());
        $this->assertSame('2026-09-10 18:00:00', $row->fresh()->check_out_time->toDateTimeString());
        $this->assertRejected($user, '2026-09-10 07:00:00');
    }

    public function test_checkout_snapshot_survives_master_change_and_removed_assignment(): void
    {
        $user = $this->employee();
        $row = $this->record($user, '2026-09-10 08:00:00');
        $user->assignedShift->update(['name' => 'Middle', 'start_time' => '12:00:00', 'end_time' => '20:00:00']);
        $shift = $user->assignedShift;
        $user->update(['shift' => null]);
        $shift->delete();
        $this->record($user, '2026-09-11 07:59:59');
        $this->assertSame('Pagi', $row->fresh()->shift_name);
        $this->assertSame('2026-09-11 07:59:59', $row->fresh()->check_out_time->toDateTimeString());
        $this->assertSame('2026-09-11 08:00:00', $row->fresh()->checkout_deadline_at->toDateTimeString());
        $this->assertRejected($user, '2026-09-11 08:00:00');
        $this->assertDatabaseCount('attendance_employee', 1);
    }

    public function test_next_start_creates_new_row_without_filling_forgotten_checkout(): void
    {
        $user = $this->employee();
        $first = $this->record($user, '2026-09-10 08:00:00');
        $second = $this->record($user, '2026-09-11 08:00:00');
        $this->assertNotSame($first->id, $second->id);
        $this->assertNull($first->fresh()->check_out_time);
        $this->assertNull($second->check_out_time);
        $this->assertDatabaseCount('attendance_employee', 2);
    }

    public function test_overnight_shift_uses_start_date_and_exclusive_next_start_deadline(): void
    {
        $user = $this->employee(['start_time' => '22:00:00', 'end_time' => '06:00:00']);
        $row = $this->record($user, '2026-09-11 02:00:00');
        $this->record($user, '2026-09-11 21:59:59');
        $this->assertSame('2026-09-10', $row->attendance_date->toDateString());
        $this->assertSame('2026-09-10 22:00:00', $row->scheduled_start_at->toDateTimeString());
        $this->assertSame('2026-09-11 06:00:00', $row->scheduled_end_at->toDateTimeString());
        $this->assertSame('2026-09-11 22:00:00', $row->checkout_deadline_at->toDateTimeString());
        $this->assertSame('2026-09-11 21:59:59', $row->fresh()->check_out_time->toDateTimeString());
        $this->assertSame('2026-09-11', $this->record($user, '2026-09-11 22:00:00')->attendance_date->toDateString());
    }

    public function test_overnight_end_is_inclusive_and_daytime_gap_rejected(): void
    {
        $shift = ['start_time' => '22:00:00', 'end_time' => '06:00:00'];
        $row = $this->record($this->employee($shift), '2026-09-11 06:00:00');
        $this->assertSame('2026-09-10', $row->attendance_date->toDateString());
        $this->assertRejected($this->employee($shift), '2026-09-11 06:00:01');
        $this->assertRejected($this->employee($shift), '2026-09-11 21:59:59');
    }

    public function test_assignment_change_does_not_open_second_row_on_same_date(): void
    {
        $user = $this->employee();
        $first = $this->record($user, '2026-09-10 08:00:00');
        $user->update(['shift' => Shift::factory()->create(['name' => 'Siang', 'start_time' => '14:00:00', 'end_time' => '22:00:00'])->id]);
        $second = $this->record($user, '2026-09-10 14:00:00');
        $this->assertSame($first->id, $second->id);
        $this->assertSame('Pagi', $second->shift_name);
        $this->assertDatabaseCount('attendance_employee', 1);
    }

    public function test_hikvision_uses_server_time_and_identical_retry_does_not_checkout(): void
    {
        $user = $this->employee();
        $payload = $this->payload($user, '2030-01-01T20:00:00+07:00');
        $this->travelTo(Carbon::parse('2026-09-10 08:00:00', 'Asia/Jakarta'));
        $this->postJson('/api/absensi', $payload)->assertOk();
        $this->travelTo(Carbon::parse('2026-09-10 12:00:00', 'Asia/Jakarta'));
        $this->postJson('/api/absensi', $payload)->assertOk();
        $row = AttendanceEmployee::query()->sole();
        $this->assertSame('2026-09-10 08:00:00', $row->check_in_time->toDateTimeString());
        $this->assertNull($row->check_out_time);
        $this->assertSame('Nama Perangkat', $row->nama_di_alat);
        $this->assertSame(DeviceEvent::query()->sole()->id, $row->device_event_id);
        $this->assertDatabaseCount('attendances', 0);
        $this->travelTo(Carbon::parse('2026-09-10 18:00:00', 'Asia/Jakarta'));
        $this->postJson('/api/absensi', $this->payload($user, '2020-01-01T08:00:00+07:00'))->assertOk();
        $this->assertSame('2026-09-10 18:00:00', $row->fresh()->check_out_time->toDateTimeString());
    }

    public function test_rejected_device_scan_keeps_log_but_not_attendance(): void
    {
        $user = $this->employee();
        $this->travelTo(Carbon::parse('2026-09-10 07:00:00', 'Asia/Jakarta'));
        $this->postJson('/api/absensi', $this->payload($user, '2026-09-10T09:00:00+07:00'))->assertOk();
        $this->assertDatabaseCount('device_events', 1);
        $this->assertTrue(DeviceEvent::query()->sole()->is_found);
        $this->assertDatabaseCount('attendance_employee', 0);
        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_head_coach_uses_pt_shift(): void
    {
        $shift = Shift::factory()->create(['role' => 'pt', 'start_time' => '08:00:00', 'end_time' => '16:00:00']);
        $user = User::factory()->headCoach()->create(['shift' => $shift->id]);
        $this->assertSame('pt', $this->record($user, '2026-09-10 08:00:00')->shift_role);
    }

    public function test_device_deletion_preserves_attendance(): void
    {
        $user = $this->employee();
        $this->travelTo(Carbon::parse('2026-09-10 08:00:00', 'Asia/Jakarta'));
        $this->postJson('/api/absensi', $this->payload($user, '2026-09-10T08:00:00+07:00'))->assertOk();
        $row = AttendanceEmployee::query()->sole();
        DeviceEvent::query()->sole()->delete();
        $this->assertModelExists($row);
        $this->assertNull($row->fresh()->device_event_id);
    }

    public function test_identical_second_scans_keep_single_row_and_use_database_lock(): void
    {
        $user = $this->employee();
        DB::enableQueryLog();
        $first = $this->record($user, '2026-09-10 08:00:00');
        $second = $this->record($user, '2026-09-10 08:00:00');
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        $this->assertSame($first->id, $second->id);
        $this->assertNull($second->check_out_time);
        $this->assertDatabaseCount('attendance_employee', 1);
        $this->assertTrue(collect($queries)->contains(fn (array $query): bool => str_contains(strtolower($query['query']), 'users') && str_contains(strtolower($query['query']), 'for update')));
    }

    /** @param array<string, mixed> $attributes */
    private function employee(array $attributes = []): User
    {
        $shift = Shift::factory()->create(['code' => 'P', 'name' => 'Pagi', 'role' => 'admin', 'start_time' => '08:00:00', 'end_time' => '16:00:00', ...$attributes]);

        return User::factory()->create(['role' => $shift->role, 'shift' => $shift->id]);
    }

    private function record(User $user, string $time): AttendanceEmployee
    {
        return app(EmployeeAttendanceService::class)->record($user, Carbon::parse($time, 'Asia/Jakarta'));
    }

    private function assertRejected(User $user, string $time): void
    {
        try {
            $this->record($user, $time);
            $this->fail('Invalid employee check-in must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('attendance', $exception->errors());
        }
    }

    /** @return array<string, mixed> */
    private function payload(User $user, string $time): array
    {
        return ['eventType' => 'AccessControllerEvent', 'dateTime' => $time, 'AccessControllerEvent' => ['employeeNoString' => (string) $user->id, 'name' => 'Nama Perangkat', 'attendanceStatus' => 'checkIn', 'currentVerifyMode' => 'cardOrFaceOrFp']];
    }
}
