<?php

namespace Tests\Feature;

use App\EmployeeAttendanceService;
use App\EmployeeAttendanceStatus;
use App\Models\AttendanceEmployee;
use App\Models\DeviceEvent;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

class EmployeeAttendanceCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-09-12 12:00:00', 'Asia/Jakarta'));
        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    public function test_admin_can_create_read_edit_cancel_delete_and_delete_future_absences(): void
    {
        $employee = User::factory()->create(['role' => 'pt', 'shift' => null]);
        $component = $this->page()->set('month', '2026-10')->set('search', $employee->name)
            ->call('openAttendanceCell', $employee->id, '2026-10-01')
            ->set('form.status', 'izin')->set('form.notes', 'Urus keluarga')
            ->call('saveAttendanceCell')->assertHasNoErrors()->assertSee('IZIN')
            ->assertSet('month', '2026-10')->assertSet('search', $employee->name);
        $record = AttendanceEmployee::firstOrFail();
        $this->assertSame(EmployeeAttendanceStatus::Izin, $record->status);
        $this->assertNull($record->check_in_time);
        $this->assertNull($record->shift_code);
        $component->call('openAttendanceCell', $employee->id, '2026-10-01')->assertSee('Urus keluarga')
            ->call('editAttendanceCell')->set('form.status', 'off')->call('saveAttendanceCell')->assertHasNoErrors()->assertSee('OFF')
            ->call('openAttendanceCell', $employee->id, '2026-10-01')->call('confirmDeleteAttendanceCell')
            ->assertSee('Sel akan kembali kosong.')->set('confirmingCellDeletion', false);
        $this->assertDatabaseCount('attendance_employee', 1);
        $component->call('confirmDeleteAttendanceCell')->call('deleteAttendanceCell')->assertHasNoErrors();
        $this->assertDatabaseCount('attendance_employee', 0);
    }

    public function test_past_cell_prefills_dates_and_allows_presence_creation_edit_and_deletion(): void
    {
        $employee = $this->employee();
        $component = $this->page()->call('openAttendanceCell', $employee->id, '2026-09-05')
            ->assertSeeHtml('value="2026-09-05"')->assertSeeHtml('type="time"')
            ->assertSet('form.checkIn', '')->assertSet('form.checkOut', '')
            ->set('form.checkIn', '2026-09-05T08:30')->call('saveAttendanceCell')->assertHasNoErrors();
        $record = AttendanceEmployee::firstOrFail();
        $this->assertSame('2026-09-05', $record->attendance_date->toDateString());
        $this->assertNull($record->check_out_time);
        $component->call('openAttendanceCell', $employee->id, '2026-09-05')->call('editAttendanceCell')
            ->assertSet('form.checkIn', '2026-09-05T08:30')
            ->set('form.checkOut', '2026-09-05T17:00')->call('saveAttendanceCell')->assertHasNoErrors();
        $this->assertSame('2026-09-05 17:00', $record->fresh()->check_out_time->format('Y-m-d H:i'));
        $component->call('openAttendanceCell', $employee->id, '2026-09-05')->call('confirmDeleteAttendanceCell')
            ->call('deleteAttendanceCell')->assertHasNoErrors();
        $this->assertDatabaseMissing('attendance_employee', ['id' => $record->id]);
    }

    public function test_invalid_time_dispatches_focus_target_and_shows_inline_error(): void
    {
        $employee = $this->employee();
        $this->page()->call('openAttendanceCell', $employee->id, '2026-09-05')
            ->set('form.checkIn', '2026-09-05T08:30')->set('form.checkOut', '2026-09-05T07:00')
            ->call('saveAttendanceCell')->assertHasErrors('form.checkOut')
            ->assertDispatched('attendance-cell-invalid', field: 'form.checkOut')
            ->assertSeeHtml('id="cell-checkOut-error"')
            ->assertSee('Jam keluar harus setelah masuk')
            ->assertSet('editingCell', true);
        $this->assertDatabaseCount('attendance_employee', 0);
    }

    public function test_sakit_can_be_created_edited_and_deleted_and_totals_combine_izin(): void
    {
        $employee = User::factory()->create(['role' => 'pt']);
        AttendanceEmployee::create(['user_id' => $employee->id, 'attendance_date' => '2026-09-09', 'status' => 'izin']);
        $component = $this->page()->call('openAttendanceCell', $employee->id, '2026-09-10')
            ->set('form.status', 'sakit')->set('form.notes', 'Istirahat')
            ->call('saveAttendanceCell')->assertHasNoErrors()->assertSee('SAKIT')->assertSee('Izin/Sakit');
        $record = AttendanceEmployee::where('attendance_date', '2026-09-10')->firstOrFail();
        $this->assertSame(EmployeeAttendanceStatus::Sakit, $record->status);
        $this->assertNull($record->check_in_time);
        $this->assertNull($record->shift_code);
        $this->assertSame(['hadir' => 0, 'off' => 0, 'izin' => 2, 'all' => 2], $component->instance()->with()['totals'][$employee->id]);
        $component->call('openAttendanceCell', $employee->id, '2026-09-10')->assertSee('Istirahat')
            ->call('editAttendanceCell')->set('form.status', 'off')->call('saveAttendanceCell')->assertHasNoErrors();
        $this->assertSame(1, $component->instance()->with()['totals'][$employee->id]['izin']);
        $component->call('openAttendanceCell', $employee->id, '2026-09-10')->call('editAttendanceCell')
            ->set('form.status', 'sakit')->call('saveAttendanceCell')->assertHasNoErrors()
            ->call('openAttendanceCell', $employee->id, '2026-09-10')->call('confirmDeleteAttendanceCell')
            ->call('deleteAttendanceCell')->assertHasNoErrors();
        $this->assertDatabaseMissing('attendance_employee', ['id' => $record->id]);
    }

    public function test_presence_can_be_created_updated_and_changed_to_absence_without_losing_event_link(): void
    {
        $employee = $this->employee();
        $component = $this->page()->call('openAttendanceCell', $employee->id, '2026-09-10')
            ->set('form.checkIn', '2026-09-10T08:15')->set('form.checkOut', '2026-09-10T17:00')
            ->call('saveAttendanceCell')->assertHasNoErrors();
        $record = AttendanceEmployee::firstOrFail();
        $event = DeviceEvent::create(['device_code' => 'crud', 'event_type' => 'AccessControllerEvent', 'payload' => '{}']);
        $record->update(['device_event_id' => $event->id]);
        $component->call('openAttendanceCell', $employee->id, '2026-09-10')->call('editAttendanceCell')
            ->set('form.checkOut', '2026-09-10T18:00')->call('saveAttendanceCell')->assertHasNoErrors();
        $this->assertSame('18:00', $record->fresh()->check_out_time->format('H:i'));
        $component->call('openAttendanceCell', $employee->id, '2026-09-10')->call('editAttendanceCell')
            ->set('form.status', 'izin')->assertSee('akan mengosongkan')->call('saveAttendanceCell')->assertHasNoErrors();
        $record->refresh();
        $this->assertSame($event->id, $record->device_event_id);
        $this->assertNull($record->check_in_time);
        $this->assertNull($record->check_out_time);
        $this->assertNull($record->scheduled_start_at);
        $this->assertNull($record->shift_code);
        $component->call('openAttendanceCell', $employee->id, '2026-09-10')->call('editAttendanceCell')
            ->set('form.status', 'hadir')->set('form.checkIn', '2026-09-10T09:00')->call('saveAttendanceCell')->assertHasNoErrors();
        $this->assertSame(EmployeeAttendanceStatus::Hadir, $record->fresh()->status);
    }

    public function test_existing_snapshot_survives_master_shift_change(): void
    {
        $employee = $this->employee();
        $record = app(EmployeeAttendanceService::class)->record($employee, Carbon::parse('2026-09-10 08:15:23'));
        $employee->assignedShift->update(['name' => 'Renamed', 'start_time' => '12:00:00']);
        $this->page()->call('openAttendanceCell', $employee->id, '2026-09-10')->call('editAttendanceCell')
            ->set('form.notes', 'Koreksi catatan')->call('saveAttendanceCell')->assertHasNoErrors();
        $record->refresh();
        $this->assertSame('Pagi', $record->shift_name);
        $this->assertSame('08:00:00', $record->shift_start_time);
        $this->assertSame('08:15:23', $record->check_in_time->format('H:i:s'));
    }

    public function test_wrong_roles_cannot_mutate_and_member_cannot_be_targeted(): void
    {
        $employee = $this->employee();
        foreach ([User::factory()->create(['role' => 'kasir_gym']), User::factory()->headCoach()->create()] as $viewer) {
            $this->actingAs($viewer);
            $this->page()->call('openAttendanceCell', $employee->id, '2026-09-10')->call('saveAttendanceCell')->assertForbidden();
            $this->page()->call('openAttendanceCell', $employee->id, '2026-09-10')->call('deleteAttendanceCell')->assertForbidden();
        }
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $member = User::factory()->create(['role' => 'member']);
        try {
            $this->page()->call('openAttendanceCell', $member->id, '2026-09-10');
            $this->fail('Member must not be a target.');
        } catch (ModelNotFoundException $exception) {
            $this->assertSame(User::class, $exception->getModel());
        }
        Livewire::test('pages::dashboard.admin.absensi.index')->call('openAttendanceCell', $employee->id, '2026-09-10')->assertForbidden();
        $this->assertDatabaseCount('attendance_employee', 0);
    }

    public function test_stale_edits_and_deletions_do_not_overwrite_scanner_changes(): void
    {
        $employee = $this->employee();
        $component = $this->page()->call('openAttendanceCell', $employee->id, '2026-09-10')->set('form.status', 'off');
        $record = app(EmployeeAttendanceService::class)->record($employee, Carbon::parse('2026-09-10 08:00:00'));
        $component->call('saveAttendanceCell')->assertHasErrors('conflict')
            ->call('reloadAttendanceCell')->call('confirmDeleteAttendanceCell');
        app(EmployeeAttendanceService::class)->record($employee, Carbon::parse('2026-09-10 09:00:00'));
        $component->call('deleteAttendanceCell')->assertHasErrors('conflict');
        $this->assertDatabaseCount('attendance_employee', 1);
        $this->assertSame('09:00', $record->fresh()->check_out_time->format('H:i'));
    }

    public function test_validation_enforces_dates_times_role_shift_and_note_limit(): void
    {
        $employee = $this->employee();
        $component = $this->page()->call('openAttendanceCell', $employee->id, '2026-09-10')
            ->set('form.checkIn', '2026-09-10T07:00')->call('saveAttendanceCell')->assertHasErrors('form.checkIn')
            ->set('form.checkIn', '2026-09-10T08:00')->set('form.checkOut', '2026-09-10T07:00')->call('saveAttendanceCell')->assertHasErrors('form.checkOut')
            ->set('form.checkOut', '2026-09-11T08:00')->call('saveAttendanceCell')->assertHasErrors('form.checkOut');
        $wrongShift = Shift::factory()->create(['role' => 'sales']);
        $component->set('form.checkOut', '')->set('form.shift', (string) $wrongShift->id)->call('saveAttendanceCell')->assertHasErrors('form.shift')
            ->set('form.status', 'off')->set('form.notes', str_repeat('a', 1001))->call('saveAttendanceCell')->assertHasErrors('form.notes')
            ->set('form.status', 'unknown')->call('saveAttendanceCell')->assertHasErrors('form.status');
        $this->page()->call('openAttendanceCell', $employee->id, '2026-10-01')->set('form.checkIn', '2026-10-01T08:00')
            ->call('saveAttendanceCell')->assertHasErrors('form.checkIn');
        $this->assertDatabaseCount('attendance_employee', 0);
    }

    public function test_manual_overnight_attendance_uses_selected_start_date(): void
    {
        $employee = $this->employee();
        $employee->assignedShift->update(['start_time' => '22:00:00', 'end_time' => '06:00:00']);
        $this->page()->call('openAttendanceCell', $employee->id, '2026-09-10')
            ->set('form.checkIn', '2026-09-11T02:00')->set('form.checkOut', '2026-09-11T07:00')
            ->call('saveAttendanceCell')->assertHasNoErrors();
        $record = AttendanceEmployee::firstOrFail();
        $this->assertSame('2026-09-10', $record->attendance_date->toDateString());
        $this->assertSame('2026-09-11 22:00', $record->checkout_deadline_at->format('Y-m-d H:i'));
    }

    public function test_rollback_refuses_absence_data_without_mutation(): void
    {
        $record = AttendanceEmployee::create(['user_id' => $this->employee()->id, 'attendance_date' => '2026-09-10', 'status' => 'off']);
        $migration = require database_path('migrations/2026_09_10_164036_add_status_to_attendance_employee_table.php');
        try {
            $migration->down();
            $this->fail('Unsafe rollback must be refused.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Rollback dihentikan', $exception->getMessage());
        }
        $this->assertSame(EmployeeAttendanceStatus::Off, $record->fresh()->status);
    }

    public function test_database_defaults_legacy_shaped_rows_to_present_and_keeps_daily_uniqueness(): void
    {
        $attributes = AttendanceEmployee::factory()->make(['user_id' => $this->employee()->id])->getAttributes();
        unset($attributes['status']);
        $id = DB::table('attendance_employee')->insertGetId($attributes);
        $this->assertSame(EmployeeAttendanceStatus::Hadir, AttendanceEmployee::findOrFail($id)->status);
        $this->expectException(UniqueConstraintViolationException::class);
        DB::table('attendance_employee')->insert($attributes);
    }

    public function test_future_schedule_stays_pending_until_qr_check_in(): void
    {
        $employee = $this->employee();
        $page = $this->page()->set('month', '2026-10')
            ->call('openAttendanceCell', $employee->id, '2026-10-01')
            ->call('saveAttendanceCell')->assertHasNoErrors()->assertSee('bg-[#FFED00]/20', false)->assertDontSee('blur-', false);
        $row = AttendanceEmployee::query()->sole();
        $this->assertNull($row->check_in_time);
        $this->assertSame('2026-10-01 08:00', $row->scheduled_start_at->format('Y-m-d H:i'));
        $this->assertSame(0, $page->instance()->with()['totals'][$employee->id]['all']);
        $page->call('openAttendanceCell', $employee->id, '2026-10-01')->assertSee('Belum masuk')
            ->call('editAttendanceCell')->set('form.notes', 'Jadwal besok')
            ->call('saveAttendanceCell')->assertHasNoErrors();
        $page->call('openAttendanceCell', $employee->id, '2026-10-01')->call('editAttendanceCell')
            ->set('form.checkOut', '2026-10-01T16:00')->call('saveAttendanceCell')->assertHasErrors('form.checkOut');
        $page->call('closeAttendanceCell');
        $this->travelTo(Carbon::parse('2026-10-01 08:00:00', 'Asia/Jakarta'));
        $page->set('scannedCode', json_encode(['user_id' => $employee->id]))->call('processScan')->assertSee('Berhasil Check-In');
        $this->assertSame('08:00:00', $row->fresh()->check_in_time->format('H:i:s'));
        $this->assertNull($row->fresh()->check_out_time);
        $this->assertSame(1, $page->instance()->with()['totals'][$employee->id]['hadir']);
        $this->assertSame(1, $page->instance()->with()['totals'][$employee->id]['all']);
        $this->travelTo(Carbon::parse('2026-10-01 17:00:00', 'Asia/Jakarta'));
        $page->set('scannedCode', json_encode(['user_id' => $employee->id]))->call('processScan')->assertSee('Berhasil Check-Out');
        $this->assertSame('17:00:00', $row->fresh()->check_out_time->format('H:i:s'));
        $this->assertDatabaseCount('attendance_employee', 1);
    }

    public function test_pending_schedule_uses_snapshot_and_inclusive_boundaries(): void
    {
        foreach (['07:00:00', '16:00:00'] as $time) {
            $employee = $this->employee();
            $employee->assignedShift->update(['start_time' => '07:00:00']);
            $this->page()->call('openAttendanceCell', $employee->id, '2026-10-01')
                ->call('saveAttendanceCell')->assertHasNoErrors();
            $row = $employee->employeeAttendances()->sole();
            $employee->update(['shift' => null]);
            $service = app(EmployeeAttendanceService::class);
            foreach (['2026-10-01 06:59:59', '2026-10-01 16:00:01'] as $rejected) {
                try {
                    $service->record($employee, Carbon::parse($rejected));
                    $this->fail('Scan outside the scheduled shift must be rejected.');
                } catch (ValidationException) {
                    $this->assertNull($row->fresh()->check_in_time);
                    $this->assertNull($row->fresh()->check_out_time);
                }
            }
            $result = $service->record($employee, Carbon::parse('2026-10-01 '.$time));
            $this->assertSame($row->id, $result->id);
            $this->assertSame($time, $result->check_in_time->format('H:i:s'));
            $service->record($employee, Carbon::parse('2026-10-01 '.$time));
            $this->assertNull($row->fresh()->check_out_time);
        }
    }

    private function employee(): User
    {
        $shift = Shift::factory()->create(['role' => 'pt', 'code' => 'P', 'name' => 'Pagi', 'start_time' => '08:00:00', 'end_time' => '16:00:00']);

        return User::factory()->create(['role' => 'pt', 'shift' => $shift->id]);
    }

    private function page(): Testable
    {
        return Livewire::test('pages::dashboard.admin.absensi.index', ['employeesOnly' => true]);
    }
}
