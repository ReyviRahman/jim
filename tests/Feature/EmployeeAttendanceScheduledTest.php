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

    public function test_overnight_schedule_accepts_next_day_check_in_and_checkout(): void
    {
        $employee = $this->employee();
        $employee->assignedShift->update(['start_time' => '22:00:00', 'end_time' => '06:00:00']);
        $row = $this->schedule($employee, '2026-10-01');
        $employee->assignedShift->update(['start_time' => '07:00:00', 'end_time' => '16:00:00']);
        $service = app(EmployeeAttendanceService::class);
        $service->record($employee, Carbon::parse('2026-10-02 02:00:00'));
        $service->record($employee, Carbon::parse('2026-10-02 07:00:00'));
        $this->assertSame('2026-10-01', $row->fresh()->attendance_date->toDateString());
        $this->assertSame('02:00:00', $row->fresh()->check_in_time->format('H:i:s'));
        $this->assertSame('07:00:00', $row->fresh()->check_out_time->format('H:i:s'));
        $this->assertDatabaseCount('attendance_employee', 1);
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
