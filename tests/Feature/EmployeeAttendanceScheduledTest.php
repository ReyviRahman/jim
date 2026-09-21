<?php

namespace Tests\Feature;

use App\EmployeeAttendanceEditor;
use App\EmployeeAttendanceService;
use App\Models\AttendanceEmployee;
use App\Models\DeviceEvent;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class EmployeeAttendanceScheduledTest extends TestCase
{
    use RefreshDatabase;

    public function test_device_fills_existing_schedule_and_retry_does_not_checkout(): void
    {
        $employee = $this->employee();
        $row = $this->schedule($employee, '2026-10-01');
        $payload = ['eventType' => 'AccessControllerEvent', 'dateTime' => '2030-01-01T08:00:00+07:00', 'AccessControllerEvent' => ['employeeNoString' => (string) $employee->id, 'name' => 'Nama Perangkat', 'attendanceStatus' => 'checkIn', 'currentVerifyMode' => 'cardOrFaceOrFp']];
        $this->travelTo(Carbon::parse('2026-10-01 07:00:00', 'Asia/Jakarta'));
        $this->postJson('/api/absensi', $payload)->assertOk();
        $this->assertSame('07:00:00', $row->fresh()->check_in_time->format('H:i:s'));
        $this->assertSame(DeviceEvent::query()->sole()->id, $row->fresh()->device_event_id);
        $this->assertSame('Nama Perangkat', $row->fresh()->nama_di_alat);
        $this->travelTo(Carbon::parse('2026-10-01 12:00:00', 'Asia/Jakarta'));
        $this->postJson('/api/absensi', $payload)->assertOk();
        $this->assertNull($row->fresh()->check_out_time);
        $payload['dateTime'] = '2030-01-01T09:00:00+07:00';
        $this->postJson('/api/absensi', $payload)->assertOk();
        $this->assertSame('12:00:00', $row->fresh()->check_out_time->format('H:i:s'));
        $this->assertDatabaseCount('attendance_employee', 1);
        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_device_replaces_empty_schedule_shift_and_preserves_admin_notes(): void
    {
        $employee = $this->employee();
        $row = $this->schedule($employee, '2026-10-01');
        $row->update(['notes' => 'Catatan admin']);
        $shift = Shift::factory()->create(['role' => 'pt', 'name' => 'Siang', 'start_time' => '14:00:00', 'end_time' => '22:00:00']);
        $payload = ['eventType' => 'AccessControllerEvent', 'dateTime' => '2030-01-01T08:00:00+07:00', 'AccessControllerEvent' => ['employeeNoString' => (string) $employee->id, 'name' => 'Nama Perangkat', 'attendanceStatus' => 'checkIn', 'currentVerifyMode' => 'cardOrFaceOrFp']];
        $this->travelTo(Carbon::parse('2026-10-01 13:00:00', 'Asia/Jakarta'));
        $this->postJson('/api/absensi', $payload)->assertOk();
        $row->refresh();
        $this->assertSame('13:00:00', $row->check_in_time->format('H:i:s'));
        $this->assertSame($shift->code, $row->shift_code);
        $this->assertSame('Siang', $row->shift_name);
        $this->assertSame('pt', $row->shift_role);
        $this->assertSame('14:00:00', $row->shift_start_time);
        $this->assertSame('22:00:00', $row->shift_end_time);
        $this->assertSame('2026-10-01 14:00:00', $row->scheduled_start_at->toDateTimeString());
        $this->assertSame('2026-10-01 22:00:00', $row->scheduled_end_at->toDateTimeString());
        $this->assertSame('2026-10-02 14:00:00', $row->checkout_deadline_at->toDateTimeString());
        $this->assertSame('Catatan admin', $row->notes);
        $this->assertSame(DeviceEvent::query()->sole()->id, $row->device_event_id);
        $this->assertSame('Nama Perangkat', $row->nama_di_alat);
        $this->assertNull($row->check_out_time);
        $this->assertDatabaseCount('attendance_employee', 1);
    }

    public function test_scan_fills_empty_schedule_before_start_or_after_old_deadline(): void
    {
        $employee = $this->employee();
        foreach (['06:00:00', '23:00:00'] as $index => $time) {
            $date = '2026-10-0'.($index + 1);
            $row = $this->schedule($employee, $date);
            $row->update(['checkout_deadline_at' => $date.' 17:00:00']);
            $result = app(EmployeeAttendanceService::class)->record($employee, Carbon::parse($date.' '.$time));
            $this->assertSame($row->id, $result->id);
            $this->assertSame($time, $result->check_in_time->format('H:i:s'));
            $this->assertNull($result->check_out_time);
        }
        $this->assertDatabaseCount('attendance_employee', 2);
    }

    public function test_consecutive_schedules_and_older_scans_preserve_previous_times(): void
    {
        $employee = $this->employee();
        $first = $this->schedule($employee, '2026-10-01');
        $second = $this->schedule($employee, '2026-10-02');
        $service = app(EmployeeAttendanceService::class);
        $service->record($employee, Carbon::parse('2026-10-01 07:00:00'));
        $service->record($employee, Carbon::parse('2026-10-01 17:00:00'));
        $service->record($employee, Carbon::parse('2026-10-01 16:00:00'));
        $this->assertSame('17:00:00', $first->fresh()->check_out_time->format('H:i:s'));
        $service->record($employee, Carbon::parse('2026-10-02 07:00:00'));
        $this->assertSame('2026-10-01 17:00:00', $first->fresh()->check_out_time->toDateTimeString());
        $this->assertSame('2026-10-02 07:00:00', $second->fresh()->check_in_time->toDateTimeString());
        $this->assertNull($second->fresh()->check_out_time);
        $this->assertDatabaseCount('attendance_employee', 2);
    }

    public function test_previous_day_schedule_is_not_filled_by_next_day_scan(): void
    {
        $employee = $this->employee();
        $employee->assignedShift->update(['start_time' => '22:00:00', 'end_time' => '06:00:00']);
        $row = $this->schedule($employee, '2026-10-01');
        $employee->assignedShift->update(['start_time' => '07:00:00', 'end_time' => '16:00:00']);
        $service = app(EmployeeAttendanceService::class);
        $next = $service->record($employee, Carbon::parse('2026-10-02 07:00:00'));
        $this->assertSame('2026-10-01', $row->fresh()->attendance_date->toDateString());
        $this->assertNull($row->fresh()->check_in_time);
        $this->assertNull($row->fresh()->check_out_time);
        $this->assertSame('2026-10-02 07:00:00', $next->check_in_time->toDateTimeString());
        $this->assertDatabaseCount('attendance_employee', 2);
    }

    private function employee(): User
    {
        $shift = Shift::factory()->create(['role' => 'pt', 'start_time' => '07:00:00', 'end_time' => '16:00:00']);

        return User::factory()->create(['role' => 'pt', 'shift' => $shift->id]);
    }

    private function schedule(User $employee, string $date): AttendanceEmployee
    {
        $admin = User::factory()->create(['role' => 'admin']);

        return app(EmployeeAttendanceEditor::class)->save($admin, $employee->id, $date, null, ['status' => 'hadir', 'shift' => (string) $employee->shift]);
    }
}
