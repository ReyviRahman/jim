<?php

namespace Tests\Feature;

use App\Models\AttendanceEmployee;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EmployeeAttendanceMonthTest extends TestCase
{
    use RefreshDatabase;

    public function test_names_show_current_assigned_shift_code_even_for_historical_months(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $shift = Shift::factory()->create(['role' => 'pt', 'code' => 'P']);
        $employee = User::factory()->create(['role' => 'pt', 'name' => 'Coach Aditya', 'shift' => $shift->id]);
        User::factory()->create(['role' => 'pt', 'name' => 'Tanpa Shift', 'shift' => null]);
        AttendanceEmployee::factory()->create(['user_id' => $employee->id, 'attendance_date' => '2026-08-01', 'shift_code' => 'S']);

        $component = Livewire::test('pages::dashboard.admin.absensi.index', ['employeesOnly' => true])
            ->set('month', '2026-08')->assertSee('(P) Coach Aditya')
            ->assertDontSee('(S) Coach Aditya')->assertSee('Tanpa Shift')->assertDontSee('() Tanpa Shift');
        $this->assertTrue($component->instance()->with()['employeeGroups']['pt']->first()->relationLoaded('assignedShift'));
        $shift->update(['code' => 'S']);
        $component->set('search', 'Aditya')->assertSee('(S) Coach Aditya')->assertDontSee('(P) Coach Aditya');
    }

    public function test_totals_count_each_status_in_selected_month_and_refresh_after_edits(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $employee = User::factory()->create(['role' => 'pt', 'is_active' => true]);
        foreach (['hadir', 'hadir', 'off', 'izin'] as $index => $status) {
            AttendanceEmployee::factory()->create(['user_id' => $employee->id, 'attendance_date' => '2026-09-0'.($index + 1), 'status' => $status]);
        }
        AttendanceEmployee::factory()->create(['user_id' => $employee->id, 'attendance_date' => '2026-08-01']);
        $component = Livewire::test('pages::dashboard.admin.absensi.index', ['employeesOnly' => true])
            ->set('month', '2026-09')->assertSee('Total Masuk')->assertSee('All');
        $this->assertSame(['hadir' => 2, 'off' => 1, 'izin' => 1, 'all' => 4], $component->instance()->with()['totals'][$employee->id]);
        $component->call('openAttendanceCell', $employee->id, '2026-09-03')->call('editAttendanceCell')
            ->set('form.status', 'izin')->call('saveAttendanceCell')->assertHasNoErrors();
        $this->assertSame(['hadir' => 2, 'off' => 0, 'izin' => 2, 'all' => 4], $component->instance()->with()['totals'][$employee->id]);
        $component->set('month', '2026-08');
        $this->assertSame(['hadir' => 1, 'off' => 0, 'izin' => 0, 'all' => 1], $component->instance()->with()['totals'][$employee->id]);
        $component->set('month', '2026-07');
        $this->assertSame(['hadir' => 0, 'off' => 0, 'izin' => 0, 'all' => 0], $component->instance()->with()['totals'][$employee->id]);
    }

    public function test_monthly_table_only_lists_active_employees_even_when_searching_inactive_history(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]));
        User::factory()->create(['role' => 'pt', 'name' => 'Karyawan Aktif', 'is_active' => true]);
        $inactive = User::factory()->create(['role' => 'pt', 'name' => 'Karyawan Nonaktif', 'is_active' => false]);
        User::factory()->create(['role' => 'member', 'name' => 'Member Aktif', 'is_active' => true]);
        AttendanceEmployee::factory()->create(['user_id' => $inactive->id]);

        $component = Livewire::test('pages::dashboard.admin.absensi.index', ['employeesOnly' => true])
            ->assertSee('Karyawan Aktif')->assertDontSee('Karyawan Nonaktif')->assertDontSee('Member Aktif');
        $this->assertSame(1, $component->instance()->with()['employeeCount']);
        $component->set('search', 'Nonaktif')->assertSee('Tidak ada karyawan yang cocok')
            ->call('previousMonth')->assertDontSee('Karyawan Nonaktif');
        $this->assertSame(0, $component->instance()->with()['employeeCount']);
        $this->assertDatabaseHas('attendance_employee', ['user_id' => $inactive->id]);
    }

    public function test_admin_and_both_head_coach_identifiers_are_excluded_from_rows_and_totals(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'name' => 'Hidden Manager']);
        $coach = User::factory()->create(['role' => 'head_coach', 'name' => 'Hidden Role Coach']);
        $emailCoach = User::factory()->create(['role' => 'pt', 'email' => strtolower(User::HEAD_COACH_EMAIL), 'name' => 'Hidden Email Coach']);
        $employee = User::factory()->create(['role' => 'pt', 'name' => 'Visible Trainer']);
        foreach ([$admin, $coach, $emailCoach, $employee] as $user) {
            AttendanceEmployee::factory()->create(['user_id' => $user->id]);
        }
        $this->actingAs($admin);
        $component = Livewire::test('pages::dashboard.admin.absensi.index', ['employeesOnly' => true])
            ->assertSee('Visible Trainer')->assertDontSee('Hidden Manager')
            ->assertDontSee('Hidden Role Coach')->assertDontSee('Hidden Email Coach');
        $this->assertSame(1, $component->instance()->with()['employeeCount']);
        $this->assertSame([$employee->id], $component->instance()->with()['totals']->keys()->all());
        $component->set('search', 'Hidden')->assertSee('Tidak ada karyawan yang cocok');
        $this->assertDatabaseCount('attendance_employee', 4);
    }

    public function test_month_navigation_handles_year_boundaries_leap_year_and_invalid_values(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->travelTo(now()->setDate(2026, 9, 10));
        $component = Livewire::withQueryParams(['month' => 'invalid'])
            ->test('pages::dashboard.admin.absensi.index', ['employeesOnly' => true])
            ->assertSet('month', '2026-09')
            ->set('month', '2026-01')->call('previousMonth')->assertSet('month', '2025-12')
            ->call('nextMonth')->assertSet('month', '2026-01')
            ->set('month', '2024-02');
        $this->assertCount(29, $component->instance()->with()['days']);
        $component->set('month', '2026-02');
        $this->assertCount(28, $component->instance()->with()['days']);
        $component->set('month', '2026-13')->assertSet('month', '2026-09')
            ->set('month', '2025-04')->call('currentMonth')->assertSet('month', '2026-09');
    }
}
