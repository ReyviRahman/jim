<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AttendanceEmployee;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EmployeeAttendanceUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_list_only_shows_new_snapshots_without_legacy_history_control(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $employee = User::factory()->create(['role' => 'pt', 'name' => 'Current Coach']);
        AttendanceEmployee::factory()->create([
            'user_id' => $employee->id,
            'shift_code' => 'M',
            'shift_name' => 'Middle Snapshot',
            'shift_start_time' => '08:00:00',
            'shift_end_time' => '16:00:00',
            'attendance_date' => '2026-09-10',
        ]);
        $legacy = User::factory()->create(['role' => 'pt', 'name' => 'Legacy Coach']);
        Attendance::create(['user_id' => $legacy->id, 'check_in_time' => '2026-09-09 08:00:00']);

        $component = Livewire::test('pages::dashboard.admin.absensi.index', ['employeesOnly' => true])
            ->set('month', '2026-09')->assertSee('Current Coach')->assertSee('Middle Snapshot')
            ->call('openAttendanceCell', $employee->id, '2026-09-10')->assertSee('08:00')->call('closeAttendanceCell')
            ->assertSee('Legacy Coach')
            ->assertDontSee('Histori lama')->assertDontSee('Presensi baru')
            ->set('search', 'Current')->call('setDateRange', '2026-09-10');
        $this->assertSame(1, $component->instance()->with()['employeeCount']);
        $component->set('month', '2026-08')->assertSee('Current Coach')
            ->set('search', '')->assertSee('Legacy Coach')->assertDontSee('Middle Snapshot');
        $this->assertDatabaseHas('attendances', ['user_id' => $legacy->id]);
    }

    public function test_employee_qr_check_in_and_checkout_do_not_create_member_attendance(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $shift = Shift::factory()->create(['role' => 'pt', 'start_time' => '08:00:00', 'end_time' => '16:00:00']);
        $employee = User::factory()->create(['role' => 'pt', 'shift' => $shift->id]);
        $this->travelTo(now('Asia/Jakarta')->setDate(2026, 9, 10)->setTime(8, 0));
        $component = Livewire::test('pages::dashboard.admin.absensi.index', ['employeesOnly' => true])
            ->set('month', '2026-08')
            ->set('scannedCode', json_encode(['user_id' => $employee->id]))
            ->call('processScan')->assertSee('Berhasil Check-In');
        $this->travelTo(now('Asia/Jakarta')->setTime(18, 0));
        $component->set('scannedCode', json_encode(['user_id' => $employee->id]))
            ->call('processScan')->assertSee('Berhasil Check-Out')->assertSet('month', '2026-08');
        $this->assertDatabaseCount('attendance_employee', 1);
        $this->assertDatabaseCount('attendances', 0);
        $this->assertSame('18:00', AttendanceEmployee::first()->check_out_time->format('H:i'));
    }

    public function test_employee_qr_rejection_displays_reason_without_writing_attendance(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $employee = User::factory()->create(['role' => 'sales', 'shift' => null]);
        Livewire::test('pages::dashboard.admin.absensi.index')
            ->set('scannedCode', json_encode(['user_id' => $employee->id]))
            ->call('processScan')->assertSee('Gagal!')
            ->assertSee('Shift karyawan belum diatur atau tidak sesuai role.')
            ->assertSet('scannedCode', '');
        $this->assertDatabaseCount('attendance_employee', 0);
        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_coach_history_is_private_and_retains_legacy_rows_and_checkout_indicator(): void
    {
        $coach = User::factory()->create(['role' => 'pt']);
        $this->actingAs($coach);
        $this->travelTo(now('Asia/Jakarta')->setDate(2026, 9, 10)->setTime(18, 0));
        AttendanceEmployee::factory()->create([
            'user_id' => $coach->id,
            'shift_name' => 'Own Snapshot',
            'check_in_time' => now()->subHours(10),
            'check_out_time' => now(),
        ]);
        AttendanceEmployee::factory()->create(['shift_name' => 'Other Coach Snapshot']);
        Attendance::create(['user_id' => $coach->id, 'check_in_time' => '2026-08-01 08:30:00']);

        Livewire::test('pages::dashboard.pt.kehadiran.index')
            ->assertSee('Own Snapshot')->assertDontSee('Other Coach Snapshot')
            ->set('legacyHistory', true)->assertSee('08:30')->assertDontSee('Own Snapshot');
        Livewire::test('pages::dashboard.pt.absensi.index')->assertSee('Absensi Berhasil!');
    }
}
