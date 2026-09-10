<?php

namespace Tests\Feature;

use App\EmployeeAttendanceService;
use App\EmployeeAttendanceStatus;
use App\Models\AttendanceEmployee;
use App\Models\DeviceEvent;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class EmployeeAttendanceStatusScanTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_present_status_blocks_scan_and_deleting_it_allows_check_in(): void
    {
        foreach ([EmployeeAttendanceStatus::Izin, EmployeeAttendanceStatus::Off, EmployeeAttendanceStatus::Sakit] as $status) {
            $employee = $this->employee();
            $row = $this->absence($employee, $status);
            $this->assertStatusRejected($employee, '2026-09-10 08:00:00', $status);
            $this->assertSame($status, $row->fresh()->status);
            $this->assertNull($row->fresh()->check_in_time);
            $this->assertNull($row->fresh()->check_out_time);
            $row->delete();
            $present = app(EmployeeAttendanceService::class)->record($employee, Carbon::parse('2026-09-10 08:00:00'));
            $this->assertSame(EmployeeAttendanceStatus::Hadir, $present->status);
            $this->assertSame('08:00', $present->check_in_time->format('H:i'));
        }
    }

    public function test_overnight_scan_checks_status_of_shift_start_date(): void
    {
        $employee = $this->employee(true);
        $this->absence($employee, EmployeeAttendanceStatus::Off);
        $this->assertStatusRejected($employee, '2026-09-11 02:00:00', EmployeeAttendanceStatus::Off);
        $row = app(EmployeeAttendanceService::class)->record($employee, Carbon::parse('2026-09-11 22:00:00'));
        $this->assertSame('2026-09-11', $row->attendance_date->toDateString());
        $this->assertSame(EmployeeAttendanceStatus::Hadir, $row->status);
    }

    public function test_replayed_device_event_linked_to_non_present_status_is_rejected(): void
    {
        $employee = $this->employee();
        $event = DeviceEvent::create(['device_code' => 'status-test', 'event_type' => 'AccessControllerEvent', 'employee_no' => (string) $employee->id, 'payload' => '{}']);
        $row = $this->absence($employee, EmployeeAttendanceStatus::Izin);
        $row->update(['device_event_id' => $event->id]);
        $this->assertStatusRejected($employee, '2026-09-10 12:00:00', EmployeeAttendanceStatus::Izin, $event);
        $this->assertNull($row->fresh()->check_out_time);
        $this->assertSame($event->id, $row->fresh()->device_event_id);
    }

    public function test_pt_history_counts_only_present_and_displays_absence_dates_and_statuses(): void
    {
        $employee = User::factory()->create(['role' => 'pt']);
        $this->actingAs($employee);
        $this->absence($employee, EmployeeAttendanceStatus::Izin);
        $this->absence($employee, EmployeeAttendanceStatus::Off, '2026-09-11');
        $this->absence($employee, EmployeeAttendanceStatus::Sakit, '2026-09-12');
        AttendanceEmployee::factory()->create(['user_id' => $employee->id, 'attendance_date' => '2026-09-09']);
        Livewire::test('pages::dashboard.pt.kehadiran.index')
            ->assertViewHas('totalPresent', 1)
            ->assertSee('Izin')->assertSee('Off')->assertSee('Hadir')->assertSee('Sakit')
            ->assertSee('11 September 2026')->assertSee('10 September 2026');
    }

    public function test_pt_success_indicator_excludes_non_present_records(): void
    {
        $employee = User::factory()->create(['role' => 'pt']);
        $this->actingAs($employee);
        $row = $this->absence($employee, EmployeeAttendanceStatus::Izin);
        $row->update(['check_in_time' => now()]);
        Livewire::test('pages::dashboard.pt.absensi.index')->assertDontSee('Absensi Berhasil!');
    }

    private function employee(bool $overnight = false): User
    {
        $shift = Shift::factory()->create([
            'role' => 'admin',
            'start_time' => $overnight ? '22:00:00' : '08:00:00',
            'end_time' => $overnight ? '06:00:00' : '16:00:00',
        ]);

        return User::factory()->create(['role' => 'admin', 'shift' => $shift->id]);
    }

    private function absence(User $employee, EmployeeAttendanceStatus $status, string $date = '2026-09-10'): AttendanceEmployee
    {
        return AttendanceEmployee::create(['user_id' => $employee->id, 'attendance_date' => $date, 'status' => $status]);
    }

    private function assertStatusRejected(User $employee, string $time, EmployeeAttendanceStatus $status, ?DeviceEvent $event = null): void
    {
        try {
            app(EmployeeAttendanceService::class)->record($employee, Carbon::parse($time), $event);
            $this->fail('Scan must not replace an absence status.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString($status->label(), $exception->errors()['attendance'][0]);
            $this->assertStringContainsString('10-09-2026', $exception->errors()['attendance'][0]);
        }
    }
}
