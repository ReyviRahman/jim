<?php

namespace Tests\Feature;

use App\EmployeeAttendanceEditor;
use App\EmployeeAttendanceService;
use App\Models\AttendanceEmployee;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

class EmployeeAttendanceBulkTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-09-12 12:00:00', 'Asia/Jakarta'));
        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    public function test_selection_toggle_role_restriction_and_filters_clear_selection(): void
    {
        $employee = $this->employee();
        $other = User::factory()->create(['role' => 'kasir_gym']);
        $page = $this->page()->call('beginBulkAttendance')
            ->call('toggleAttendanceSelection', $employee->id, '2026-09-15')
            ->assertSet('bulkRole', 'pt')->assertSee('1 sel dipilih')
            ->call('toggleAttendanceSelection', $other->id, '2026-09-15')->assertHasErrors('bulkSelection');
        $this->assertCount(1, $page->get('bulkSelection'));
        $page->call('toggleAttendanceSelection', $employee->id, '2026-09-15')->assertSet('bulkSelection', [])
            ->call('toggleAttendanceSelection', $employee->id, '2026-10-15')->assertHasErrors('bulkSelection')
            ->call('toggleAttendanceSelection', $employee->id, '2026-09-15')->set('search', 'Nobody')->assertSet('bulkSelection', [])
            ->set('search', '')->call('beginBulkAttendance')->call('toggleAttendanceSelection', $employee->id, '2026-09-15')
            ->call('nextMonth')->assertSet('bulkSelection', [])->assertSet('selectingAttendance', false);
        $page->call('beginBulkAttendance')->call('toggleAttendanceSelection', $employee->id, '2026-10-15')
            ->set('month', '2026-11')->assertSet('bulkSelection', []);
    }

    public function test_future_bulk_create_defaults_shared_shift_and_preserves_form_on_close(): void
    {
        $first = $this->employee();
        $second = User::factory()->create(['role' => 'pt', 'shift' => $first->shift]);
        $page = $this->page()->call('beginBulkAttendance');
        foreach ([[$first, '2026-09-15'], [$first, '2026-09-16'], [$second, '2026-09-15']] as [$employee, $date]) {
            $page->call('toggleAttendanceSelection', $employee->id, $date);
        }
        $page->call('openBulkAttendance')->assertSet('bulkForm.shift', (string) $first->shift)
            ->set('bulkForm.notes', 'Jadwal bersama')->call('closeBulkAttendance')->assertSet('bulkDialogOpen', false);
        $this->assertCount(3, $page->get('bulkSelection'));
        $page->call('openBulkAttendance')->assertSet('bulkForm.notes', 'Jadwal bersama')
            ->call('saveBulkAttendance')->assertHasNoErrors()->assertSee('3 absensi berhasil dibuat.')
            ->assertSet('bulkSelection', [])->assertSet('selectingAttendance', false);
        $this->assertDatabaseCount('attendance_employee', 3);
        foreach (AttendanceEmployee::all() as $row) {
            $this->assertNull($row->check_in_time);
            $this->assertSame('Jadwal bersama', $row->notes);
            $this->assertSame('07:00:00', $row->shift_start_time);
        }
        $this->assertSame(0, $page->instance()->with()['totals'][$first->id]['all']);
        $page->call('saveBulkAttendance')->assertHasErrors('bulkSelection');
        $this->assertDatabaseCount('attendance_employee', 3);
    }

    public function test_different_assignments_require_explicit_shared_shift(): void
    {
        $first = $this->employee();
        $second = $this->employee();
        $this->page()->call('beginBulkAttendance')
            ->call('toggleAttendanceSelection', $first->id, '2026-09-15')
            ->call('toggleAttendanceSelection', $second->id, '2026-09-15')
            ->call('openBulkAttendance')->assertSet('bulkForm.shift', '')
            ->call('saveBulkAttendance')->assertHasErrors('bulkForm.shift');
        $this->assertDatabaseCount('attendance_employee', 0);
    }

    public function test_batch_applies_relative_overnight_times_and_totals(): void
    {
        $employee = $this->employee();
        $employee->assignedShift->update(['start_time' => '22:00:00', 'end_time' => '06:00:00']);
        $page = $this->page()->call('beginBulkAttendance')
            ->call('toggleAttendanceSelection', $employee->id, '2026-09-09')
            ->call('toggleAttendanceSelection', $employee->id, '2026-09-10')->call('openBulkAttendance')
            ->set('bulkForm.checkIn', '02:00')->set('bulkForm.checkInDay', '1')
            ->set('bulkForm.checkOut', '07:00')->set('bulkForm.checkOutDay', '1')
            ->call('saveBulkAttendance')->assertHasNoErrors();
        $rows = AttendanceEmployee::orderBy('attendance_date')->get();
        $this->assertSame('2026-09-10 02:00:00', $rows[0]->check_in_time->toDateTimeString());
        $this->assertSame('2026-09-11 07:00:00', $rows[1]->check_out_time->toDateTimeString());
        $this->assertSame(2, $page->instance()->with()['totals'][$employee->id]['hadir']);
    }

    public function test_non_presence_statuses_ignore_hidden_times_and_shift(): void
    {
        $employee = $this->employee();
        foreach (['off', 'izin', 'sakit'] as $index => $status) {
            $this->page()->call('beginBulkAttendance')
                ->call('toggleAttendanceSelection', $employee->id, '2026-09-'.(15 + $index))->call('openBulkAttendance')
                ->set('bulkForm.checkIn', 'bad')->set('bulkForm.checkOut', 'bad')->set('bulkForm.status', $status)
                ->call('saveBulkAttendance')->assertHasNoErrors();
        }
        foreach (AttendanceEmployee::all() as $row) {
            $this->assertNull($row->check_in_time);
            $this->assertNull($row->shift_code);
        }
        $totals = $this->page()->instance()->with()['totals'][$employee->id];
        $this->assertSame(['hadir' => 0, 'off' => 1, 'izin' => 2, 'all' => 3], $totals);
    }

    public function test_scan_conflict_rolls_back_batch_and_retains_selection_and_form(): void
    {
        $employee = $this->employee();
        $page = $this->page()->call('beginBulkAttendance')
            ->call('toggleAttendanceSelection', $employee->id, '2026-09-09')
            ->call('toggleAttendanceSelection', $employee->id, '2026-09-10')->call('openBulkAttendance')
            ->set('bulkForm.notes', 'Tetap tersimpan di form');
        $scanned = app(EmployeeAttendanceService::class)->record($employee, Carbon::parse('2026-09-10 07:00:00'));
        $page->call('saveBulkAttendance')->assertHasErrors('bulkSelection')->assertSee('2026-09-10')
            ->assertSet('bulkForm.notes', 'Tetap tersimpan di form')->assertSet('bulkDialogOpen', true);
        $this->assertCount(2, $page->get('bulkSelection'));
        $this->assertDatabaseCount('attendance_employee', 1);
        $this->assertNull($scanned->fresh()->notes);
        $page->call('toggleAttendanceSelection', $employee->id, '2026-09-10')
            ->call('saveBulkAttendance')->assertHasNoErrors();
        $this->assertDatabaseCount('attendance_employee', 2);
    }

    public function test_invalid_time_on_later_date_rolls_back_earlier_cell(): void
    {
        $employee = $this->employee();
        $this->page()->call('beginBulkAttendance')
            ->call('toggleAttendanceSelection', $employee->id, '2026-09-10')
            ->call('toggleAttendanceSelection', $employee->id, '2026-09-15')->call('openBulkAttendance')
            ->set('bulkForm.checkOut', '08:00')->call('saveBulkAttendance')->assertHasErrors('bulkForm.checkOut')
            ->set('bulkForm.checkIn', '07:00')->call('saveBulkAttendance')->assertHasErrors('bulkForm.checkIn');
        $this->assertDatabaseCount('attendance_employee', 0);
    }

    public function test_server_rechecks_eligibility_and_rejects_duplicates_and_mixed_roles(): void
    {
        $employee = $this->employee();
        $member = User::factory()->create(['role' => 'member']);
        $otherRole = User::factory()->create(['role' => 'kasir_gym']);
        $cell = ['employeeId' => $employee->id, 'date' => '2026-09-15'];
        foreach ([[$cell, $cell], [$cell, ['employeeId' => $member->id, 'date' => '2026-09-15']], [$cell, ['employeeId' => $otherRole->id, 'date' => '2026-09-15']], [$cell, ['employeeId' => $employee->id, 'date' => '2026-10-01']]] as $cells) {
            try {
                app(EmployeeAttendanceEditor::class)->saveBulk(auth()->user(), $cells, '2026-09', '', ['status' => 'off']);
                $this->fail('Invalid selection must be rejected.');
            } catch (ValidationException) {
                $this->assertDatabaseCount('attendance_employee', 0);
            }
        }
        $page = $this->page()->call('beginBulkAttendance')->call('toggleAttendanceSelection', $employee->id, '2026-09-15')->call('openBulkAttendance');
        $employee->update(['is_active' => false]);
        $page->call('saveBulkAttendance')->assertHasErrors('bulkSelection');
        $this->assertDatabaseCount('attendance_employee', 0);
    }

    public function test_non_admin_cannot_use_bulk_actions(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'kasir_gym']));
        $this->page()->assertDontSee('Pilih beberapa sel')->call('beginBulkAttendance')->assertForbidden();
        $this->page()->call('saveBulkAttendance')->assertForbidden();
    }

    public function test_client_cannot_replace_locked_selection(): void
    {
        $this->expectException(CannotUpdateLockedPropertyException::class);
        $this->page()->set('bulkSelection', ['forged' => ['employeeId' => 1, 'date' => '2026-09-15']]);
    }

    public function test_existing_cell_cannot_be_selected_or_created_again(): void
    {
        $employee = $this->employee();
        AttendanceEmployee::factory()->create(['user_id' => $employee->id, 'attendance_date' => '2026-09-10', 'check_in_time' => '2026-09-10 08:00:00']);
        $this->page()->call('beginBulkAttendance')->call('toggleAttendanceSelection', $employee->id, '2026-09-10')
            ->assertHasErrors('bulkSelection')->assertSet('bulkSelection', []);
        try {
            app(EmployeeAttendanceEditor::class)->saveBulk(auth()->user(), [['employeeId' => $employee->id, 'date' => '2026-09-10']], '2026-09', '', ['status' => 'off']);
            $this->fail('Occupied cell must be rejected.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('attendance_employee', 1);
        }
    }

    private function employee(): User
    {
        $shift = Shift::factory()->create(['role' => 'pt', 'code' => 'P', 'start_time' => '07:00:00', 'end_time' => '16:00:00']);

        return User::factory()->create(['role' => 'pt', 'shift' => $shift->id]);
    }

    private function page(): Testable
    {
        return Livewire::test('pages::dashboard.admin.absensi.index', ['employeesOnly' => true])->set('month', '2026-09');
    }
}
