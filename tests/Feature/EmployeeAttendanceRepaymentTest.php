<?php

namespace Tests\Feature;

use App\EmployeeAttendanceRepayments;
use App\Models\AttendanceEmployee;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EmployeeAttendanceRepaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_repeated_earlier_shifts_pay_each_outstanding_day_without_reusing_minutes(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $employee = User::factory()->create(['role' => 'pt', 'is_active' => true]);
        $first = $this->attendance($employee, '2026-11-03', [
            'check_in_time' => '2026-11-03 17:10:00', 'check_out_time' => '2026-11-03 22:14:00',
        ]);
        $second = $this->attendance($employee, '2026-11-05', [
            'check_in_time' => '2026-11-05 17:08:00', 'check_out_time' => '2026-11-05 22:14:00',
        ]);
        $this->attendance($employee, '2026-11-06', [
            'shift_code' => 'S', 'scheduled_start_at' => '2026-11-06 14:00:00',
            'check_in_time' => '2026-11-06 13:58:00', 'check_out_time' => '2026-11-06 22:17:00',
        ]);
        $last = $this->attendance($employee, '2026-11-07', [
            'shift_code' => 'S', 'scheduled_start_at' => '2026-11-07 14:00:00',
            'check_in_time' => '2026-11-07 13:02:00',
        ]);

        $balances = $this->balances($employee);
        $this->assertSame(10, $balances[$first->id]['remainingMinutes']);
        $this->assertSame([
            ['date' => '2026-11-06', 'minutes' => 60], ['date' => '2026-11-07', 'minutes' => 60],
        ], $balances[$first->id]['shiftPayments']);
        $this->assertSame(128, $balances[$second->id]['remainingMinutes']);
        $this->assertSame([], $balances[$second->id]['shiftPayments']);

        $page = Livewire::test('pages::dashboard.admin.absensi.index', ['employeesOnly' => true])
            ->set('month', '2026-11')->call('openAttendanceCell', $employee->id, '2026-11-03')
            ->assertSet('cellDetail.lateRepaid', false)->assertSet('cellDetail.remainingLateDuration', '10 menit')
            ->assertDontSeeHtml('border-green-600')->assertSeeHtml('border-2 border-red-600')
            ->call('openAttendanceCell', $employee->id, '2026-11-05')
            ->assertSet('cellDetail.remainingLateDuration', '2 jam 8 menit')
            ->assertSet('cellDetail.shiftPayments', []);
        $this->assertSame('2 jam 18 menit', $page->instance()->with()['lateDurations'][$employee->id]);

        $last->delete();
        $page->call('reloadAttendanceCell')->assertSet('cellDetail.remainingLateDuration', '2 jam 8 menit');
        $this->assertSame('3 jam 18 menit', $page->instance()->with()['lateDurations'][$employee->id]);
    }

    public function test_each_debt_uses_its_own_shift_and_payment_time_is_not_counted_twice(): void
    {
        $employee = User::factory()->create(['role' => 'pt']);
        $first = $this->attendance($employee, '2026-11-01', [
            'check_in_time' => '2026-11-01 15:30:00',
        ]);
        $second = $this->attendance($employee, '2026-11-02', [
            'shift_code' => 'M', 'scheduled_start_at' => '2026-11-02 16:00:00',
            'check_in_time' => '2026-11-02 18:00:00',
        ]);
        $this->attendance($employee, '2026-11-03', [
            'shift_code' => 'S', 'scheduled_start_at' => '2026-11-03 14:00:00',
            'check_in_time' => '2026-11-03 14:00:00',
        ]);

        $balances = $this->balances($employee);
        $this->assertSame(0, $balances[$first->id]['remainingMinutes']);
        $this->assertSame([['date' => '2026-11-03', 'minutes' => 30]], $balances[$first->id]['shiftPayments']);
        $this->assertSame(30, $balances[$second->id]['remainingMinutes']);
        $this->assertSame([['date' => '2026-11-03', 'minutes' => 90]], $balances[$second->id]['shiftPayments']);
    }

    public function test_same_shift_early_arrival_does_not_repay_previous_debt(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $employee = User::factory()->create(['role' => 'pt', 'is_active' => true]);
        $this->attendance($employee, '2026-11-05', [
            'scheduled_start_at' => '2026-11-05 14:00:00', 'check_in_time' => '2026-11-05 15:00:00',
        ]);
        $this->attendance($employee, '2026-11-06', [
            'scheduled_start_at' => '2026-11-06 14:00:00', 'check_in_time' => '2026-11-06 13:30:00',
        ]);

        $component = Livewire::test('pages::dashboard.admin.absensi.index', ['employeesOnly' => true])
            ->set('month', '2026-11')->call('openAttendanceCell', $employee->id, '2026-11-05')
            ->assertSet('cellDetail.lateDuration', '1 jam')
            ->assertSet('cellDetail.remainingLateDuration', '1 jam')
            ->assertSet('cellDetail.shiftPayments', [])
            ->assertDontSee('Shift lebih awal');
        $this->assertSame('1 jam', $component->instance()->with()['lateDurations'][$employee->id]);
    }

    public function test_aditya_example_displays_sources_and_recalculates_after_edits_and_deletion(): void
    {
        $this->travelTo(Carbon::parse('2026-11-07 12:00:00'));
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $employee = User::factory()->create(['role' => 'pt', 'is_active' => true, 'name' => 'Coach Aditya']);
        $thursday = $this->attendance($employee, '2026-11-05', [
            'check_in_time' => '2026-11-05 17:08:00', 'check_out_time' => '2026-11-05 22:14:00',
        ]);
        $this->attendance($employee, '2026-11-06', [
            'shift_code' => 'S', 'shift_name' => 'Siang', 'shift_start_time' => '14:00:00',
            'scheduled_start_at' => '2026-11-06 14:00:00',
            'check_in_time' => '2026-11-06 13:58:00', 'check_out_time' => '2026-11-06 22:17:00',
        ]);

        $component = Livewire::test('pages::dashboard.admin.absensi.index', ['employeesOnly' => true])
            ->set('month', '2026-11')
            ->call('openAttendanceCell', $employee->id, '2026-11-05')
            ->assertSet('cellDetail.lateDuration', '2 jam 8 menit')
            ->assertDontSee('Pulang lewat jadwal')->assertDontSee('dibayar pada hari yang sama')
            ->assertSet('cellDetail.remainingLateDuration', '1 jam 8 menit')
            ->assertSet('cellDetail.shiftPayments', [['date' => '06/11/2026', 'duration' => '1 jam']])
            ->assertSee('Shift lebih awal 06/11/2026')->assertSee('BELUM TERBAYARKAN — sisa 1 jam 8 menit');
        $this->assertSame('1 jam 8 menit', $component->instance()->with()['lateDurations'][$employee->id]);

        $component->call('openAttendanceCell', $employee->id, '2026-11-06')->call('editAttendanceCell')
            ->set('form.checkIn', '2026-11-06T14:30')->call('saveAttendanceCell')->assertHasNoErrors()
            ->call('openAttendanceCell', $employee->id, '2026-11-05')
            ->assertSet('cellDetail.remainingLateDuration', '1 jam 38 menit')
            ->assertSet('cellDetail.shiftPayments', [['date' => '06/11/2026', 'duration' => '30 menit']]);
        $this->assertSame('2 jam 8 menit', $component->instance()->with()['lateDurations'][$employee->id]);

        $component->call('openAttendanceCell', $employee->id, '2026-11-06')
            ->call('confirmDeleteAttendanceCell')->call('deleteAttendanceCell')->assertHasNoErrors()
            ->call('openAttendanceCell', $employee->id, '2026-11-05')
            ->assertSet('cellDetail.remainingLateDuration', '2 jam 8 menit')
            ->assertSet('cellDetail.shiftPayments', []);
        $this->assertSame('2 jam 8 menit', $component->instance()->with()['lateDurations'][$employee->id]);
        $this->assertModelExists($thursday);
    }

    #[DataProvider('shiftArrivalCases')]
    public function test_earlier_shift_payment_is_capped_by_schedule_and_arrival(string $code, string $start, string $arrival, int $paid, int $newLate): void
    {
        $employee = User::factory()->create(['role' => 'pt']);
        $debt = $this->attendance($employee, '2026-11-05', ['check_in_time' => '2026-11-05 17:00:00']);
        $next = $this->attendance($employee, '2026-11-06', [
            'shift_code' => $code, 'scheduled_start_at' => '2026-11-06 '.$start,
            'check_in_time' => '2026-11-06 '.$arrival,
        ]);
        $balances = $this->balances($employee);

        $this->assertSame(120 - $paid, $balances[$debt->id]['remainingMinutes']);
        $this->assertSame($paid, array_sum(array_column($balances[$debt->id]['shiftPayments'], 'minutes')));
        $this->assertSame($newLate, $balances[$next->id]['lateMinutes']);
        $this->assertNull($next->check_out_time);
    }

    public static function shiftArrivalCases(): array
    {
        return [
            'early arrival is capped' => ['S', '14:00:00', '13:58:00', 60, 0],
            'on time' => ['S', '14:00:00', '14:00:00', 60, 0],
            'late arrival pays only time gained' => ['S', '14:00:00', '14:30:00', 30, 30],
            'arrival at previous start' => ['S', '14:00:00', '15:00:00', 0, 60],
            'arrival after previous start' => ['S', '14:00:00', '15:10:00', 0, 70],
            'same shift' => ['SO', '15:00:00', '14:30:00', 0, 0],
            'same shift with changed schedule' => ['SO', '14:00:00', '13:30:00', 0, 0],
            'later shift' => ['M', '16:00:00', '14:00:00', 0, 0],
            'different shift with equal start' => ['S', '15:00:00', '14:00:00', 0, 0],
            'much earlier arrival is still capped' => ['S', '14:00:00', '00:30:00', 60, 0],
        ];
    }

    public function test_payments_clear_oldest_debts_once_and_unused_credit_is_not_saved(): void
    {
        $employee = User::factory()->create(['role' => 'pt']);
        $second = $this->attendance($employee, '2026-11-02', ['check_in_time' => '2026-11-02 16:00:00']);
        $first = $this->attendance($employee, '2026-11-01', ['check_in_time' => '2026-11-01 15:30:00']);
        $this->attendance($employee, '2026-11-03', [
            'shift_code' => 'S', 'scheduled_start_at' => '2026-11-03 14:00:00', 'check_in_time' => '2026-11-03 13:00:00',
        ]);
        $balances = $this->balances($employee);
        $this->assertSame(0, $balances[$first->id]['remainingMinutes']);
        $this->assertSame(30, $balances[$second->id]['remainingMinutes']);
        $this->assertSame([['date' => '2026-11-03', 'minutes' => 30]], $balances[$first->id]['shiftPayments']);

        $this->attendance($employee, '2026-11-04', [
            'shift_code' => 'P', 'scheduled_start_at' => '2026-11-04 13:00:00', 'check_in_time' => '2026-11-04 12:00:00',
        ]);
        $future = $this->attendance($employee, '2026-11-05', [
            'shift_code' => 'P', 'scheduled_start_at' => '2026-11-05 13:00:00', 'check_in_time' => '2026-11-05 14:00:00',
        ]);
        $balances = $this->balances($employee);
        $this->assertSame(0, $balances[$second->id]['remainingMinutes']);
        $this->assertSame([
            ['date' => '2026-11-03', 'minutes' => 30], ['date' => '2026-11-04', 'minutes' => 30],
        ], $balances[$second->id]['shiftPayments']);
        $this->assertSame(60, $balances[$future->id]['remainingMinutes']);
        $this->assertSame([], $balances[$future->id]['shiftPayments']);
    }

    public function test_absences_and_pending_scans_do_not_pay_and_historical_schedule_is_used(): void
    {
        $shift = Shift::factory()->create(['role' => 'pt', 'code' => 'NEW', 'start_time' => '08:00:00']);
        $employee = User::factory()->create(['role' => 'pt', 'shift' => $shift->id]);
        $debt = $this->attendance($employee, '2026-11-01', ['check_in_time' => '2026-11-01 16:00:00']);
        foreach (['off', 'izin', 'sakit'] as $index => $status) {
            $this->attendance($employee, '2026-11-0'.($index + 2), ['status' => $status]);
        }
        $this->attendance($employee, '2026-11-05', ['check_in_time' => null]);
        $this->attendance($employee, '2026-11-06', [
            'shift_code' => 'S', 'scheduled_start_at' => '2026-11-06 14:00:00', 'check_in_time' => '2026-11-06 13:00:00',
        ]);
        $this->assertSame(0, $this->balances($employee)[$debt->id]['remainingMinutes']);
    }

    public function test_month_boundaries_and_other_users_cannot_repay_debt(): void
    {
        $employee = User::factory()->create(['role' => 'pt']);
        $other = User::factory()->create(['role' => 'pt']);
        $debt = $this->attendance($employee, '2026-10-31', ['check_in_time' => '2026-10-31 16:00:00']);
        $firstNovember = $this->attendance($employee, '2026-11-01', [
            'shift_code' => 'S', 'scheduled_start_at' => '2026-11-01 14:00:00', 'check_in_time' => '2026-11-01 13:00:00',
        ]);
        $novemberDebt = $this->attendance($employee, '2026-11-02', ['check_in_time' => '2026-11-02 16:00:00']);
        $this->attendance($other, '2026-11-02');
        $this->attendance($other, '2026-11-03', [
            'shift_code' => 'S', 'scheduled_start_at' => '2026-11-03 14:00:00', 'check_in_time' => '2026-11-03 13:00:00',
        ]);

        $this->assertSame(60, $this->balances($employee, '2026-10')[$debt->id]['remainingMinutes']);
        $balances = $this->balances($employee);
        $this->assertArrayNotHasKey($debt->id, $balances);
        $this->assertSame(0, $balances[$firstNovember->id]['remainingMinutes']);
        $this->assertSame(60, $balances[$novemberDebt->id]['remainingMinutes']);
    }

    public function test_missing_schedule_or_check_in_does_not_generate_payment(): void
    {
        $employee = User::factory()->create(['role' => 'pt']);
        $debt = $this->attendance($employee, '2026-11-01', ['check_in_time' => '2026-11-01 16:00:00']);
        $missing = $this->attendance($employee, '2026-11-02', [
            'shift_code' => 'S', 'scheduled_start_at' => null, 'check_in_time' => '2026-11-02 14:00:00',
        ]);
        $this->assertSame(60, $this->balances($employee)[$debt->id]['remainingMinutes']);
        $this->attendance($employee, '2026-11-03', [
            'shift_code' => 'P', 'scheduled_start_at' => '2026-11-03 13:00:00', 'check_in_time' => '2026-11-03 13:00:00',
        ]);
        $this->assertSame(0, $this->balances($employee)[$debt->id]['remainingMinutes']);
        $missing->update(['scheduled_start_at' => '2026-11-02 14:00:00', 'check_in_time' => null]);
        $this->assertArrayNotHasKey($missing->id, $this->balances($employee));
    }

    public function test_overnight_shifts_use_actual_dates_and_do_not_repay_next_day_arrivals(): void
    {
        config(['app.timezone' => 'Asia/Jakarta']);
        $employee = User::factory()->create(['role' => 'pt']);
        $debt = $this->attendance($employee, '2026-11-05', [
            'shift_code' => 'M', 'scheduled_start_at' => '2026-11-05 22:00:00',
            'scheduled_end_at' => '2026-11-06 06:00:00',
            'check_in_time' => '2026-11-05 23:30:00', 'check_out_time' => '2026-11-06 06:15:00',
        ]);
        $next = $this->attendance($employee, '2026-11-06', [
            'shift_code' => 'SO', 'scheduled_start_at' => '2026-11-06 21:00:00',
            'scheduled_end_at' => '2026-11-07 05:00:00', 'check_in_time' => '2026-11-06 20:50:00',
        ]);
        $this->assertSame(30, $this->balances($employee)[$debt->id]['remainingMinutes']);
        $next->update(['check_in_time' => '2026-11-07 00:30:00']);
        $this->assertSame(90, $this->balances($employee)[$debt->id]['remainingMinutes']);
    }

    public function test_earlier_shift_payment_is_visible_without_checkout_and_can_fully_clear_debt(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $employee = User::factory()->create(['role' => 'pt']);
        $debt = $this->attendance($employee, '2026-11-05', [
            'check_in_time' => '2026-11-05 16:30:00', 'scheduled_end_at' => null,
        ]);
        $this->attendance($employee, '2026-11-06', [
            'shift_code' => 'S', 'scheduled_start_at' => '2026-11-06 14:00:00', 'check_in_time' => '2026-11-06 13:00:00',
        ]);
        $component = Livewire::test('pages::dashboard.admin.absensi.index', ['employeesOnly' => true])
            ->set('month', '2026-11')
            ->call('openAttendanceCell', $employee->id, '2026-11-05')
            ->assertSet('cellDetail.remainingLateDuration', '30 menit')
            ->assertSee('BELUM TERBAYARKAN — sisa 30 menit')->assertSee('Shift lebih awal 06/11/2026')
            ->assertSeeHtml('border-2 border-red-600')->assertSeeHtml('border-red-600 border-2')
            ->assertDontSeeHtml('border-green-600')
            ->assertDontSee('belum ada jam keluar')->assertDontSee('Belum dapat dihitung');
        $debt->update(['check_in_time' => '2026-11-05 16:00:00']);
        $component->call('reloadAttendanceCell')->assertSet('cellDetail.lateRepaid', true)
            ->assertSet('cellDetail.remainingLateDuration', '0 menit')->assertSee('SUDAH TERBAYARKAN')
            ->assertSeeHtml('border-2 border-green-600')->assertSeeHtml('border-green-600 border-2')
            ->assertDontSeeHtml('border-red-600');
        $this->assertSame('0 menit', $component->instance()->with()['lateDurations'][$employee->id]);
    }

    public function test_checkout_does_not_pay_its_own_day_or_another_day(): void
    {
        $employee = User::factory()->create(['role' => 'pt']);
        $first = $this->attendance($employee, '2026-11-01', ['check_in_time' => '2026-11-01 15:30:00']);
        $second = $this->attendance($employee, '2026-11-02', [
            'check_in_time' => '2026-11-02 15:30:00', 'check_out_time' => '2026-11-02 23:00:00',
        ]);
        $this->assertSame(30, $this->balances($employee)[$first->id]['remainingMinutes']);
        $this->assertSame(30, $this->balances($employee)[$second->id]['remainingMinutes']);
        $this->attendance($employee, '2026-11-03', [
            'shift_code' => 'S', 'scheduled_start_at' => '2026-11-03 14:00:00', 'check_in_time' => '2026-11-03 13:00:00',
        ]);
        $balances = $this->balances($employee);
        $this->assertSame(0, $balances[$first->id]['remainingMinutes']);
        $this->assertSame(0, $balances[$second->id]['remainingMinutes']);
        $this->assertSame([['date' => '2026-11-03', 'minutes' => 30]], $balances[$second->id]['shiftPayments']);
    }

    /** @param array<string, mixed> $overrides */
    private function attendance(User $employee, string $date, array $overrides = []): AttendanceEmployee
    {
        return AttendanceEmployee::factory()->create(array_replace([
            'user_id' => $employee->id, 'attendance_date' => $date,
            'shift_code' => 'SO', 'shift_name' => 'Sore', 'shift_role' => 'pt',
            'shift_start_time' => '15:00:00', 'shift_end_time' => '22:00:00',
            'scheduled_start_at' => $date.' 15:00:00', 'scheduled_end_at' => $date.' 22:00:00',
            'checkout_deadline_at' => Carbon::parse($date)->addDay()->setTime(15, 0),
            'check_in_time' => $date.' 15:00:00', 'check_out_time' => null,
        ], $overrides));
    }

    /** @return array<int, array<string, mixed>> */
    private function balances(User $employee, string $month = '2026-11'): array
    {
        return app(EmployeeAttendanceRepayments::class)->forMonth($employee, Carbon::parse($month.'-01'));
    }
}
