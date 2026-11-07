<?php

namespace App;

use App\Models\AttendanceEmployee;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class EmployeeAttendanceRepayments
{
    /**
     * @return array<int, array{date: string, lateMinutes: int, sameDayMinutes: int, remainingMinutes: int, shiftPayments: list<array{date: string, minutes: int}>}>
     */
    public function forMonth(User $employee, Carbon $date): array
    {
        $start = $date->copy()->startOfMonth();
        $end = $date->copy()->endOfMonth();
        $records = $employee->employeeAttendances()
            ->where(function (Builder $query) use ($start, $end): void {
                $query->whereBetween('attendance_date', [$start->toDateString(), $end->toDateString()])
                    ->orWhere(fn (Builder $legacy) => $legacy->whereNull('attendance_date')->whereBetween('check_in_time', [$start, $end]));
            })
            ->where('status', EmployeeAttendanceStatus::Hadir)
            ->whereNotNull('check_in_time')
            ->get();

        return $this->calculate($records);
    }

    /**
     * @param  Collection<int, AttendanceEmployee>  $records  Absensi satu karyawan dalam satu bulan.
     * @return array<int, array{date: string, lateMinutes: int, sameDayMinutes: int, remainingMinutes: int, shiftPayments: list<array{date: string, minutes: int}>}>
     */
    public function calculate(Collection $records): array
    {
        $records = $records
            ->filter(fn (AttendanceEmployee $record): bool => $record->status === EmployeeAttendanceStatus::Hadir && $record->check_in_time !== null)
            ->sortBy([
                fn (AttendanceEmployee $a, AttendanceEmployee $b): int => $this->attendanceDate($a) <=> $this->attendanceDate($b),
                ['id', 'asc'],
            ])->keyBy('id');

        $balances = [];
        foreach ($records as $record) {
            $recordDate = $this->attendanceDate($record);
            $usedMinutes = 0;
            foreach ($balances as $debtId => &$balance) {
                if ($balance['date'] >= $recordDate || $balance['remainingMinutes'] === 0) {
                    continue;
                }

                $credit = max(0, $this->shiftCreditMinutes($records[$debtId], $record) - $usedMinutes);
                if ($credit === 0) {
                    continue;
                }
                $paid = min($credit, $balance['remainingMinutes']);
                $balance['remainingMinutes'] -= $paid;
                $balance['shiftPayments'][] = ['date' => $recordDate, 'minutes' => $paid];
                $usedMinutes += $paid;
            }
            unset($balance);

            $lateMinutes = $record->isLate() ? (int) $record->scheduled_start_at->diffInMinutes($record->check_in_time) : 0;
            $sameDayMinutes = $record->check_out_time !== null && $record->scheduled_end_at !== null
                ? max(0, (int) $record->scheduled_end_at->diffInMinutes($record->check_out_time))
                : 0;
            $balances[$record->id] = [
                'date' => $recordDate,
                'lateMinutes' => $lateMinutes,
                'sameDayMinutes' => $sameDayMinutes,
                'remainingMinutes' => max(0, $lateMinutes - $sameDayMinutes),
                'shiftPayments' => [],
            ];
        }

        return $balances;
    }

    private function attendanceDate(AttendanceEmployee $record): string
    {
        return ($record->attendance_date ?? $record->check_in_time)->toDateString();
    }

    private function shiftCreditMinutes(AttendanceEmployee $debt, AttendanceEmployee $record): int
    {
        if ($debt->scheduled_start_at === null || $record->scheduled_start_at === null
            || $this->attendanceDate($debt) >= $this->attendanceDate($record)) {
            return 0;
        }

        $debtShift = $debt->shift_code ?: $debt->shift_name;
        $currentShift = $record->shift_code ?: $record->shift_name;
        if (blank($debtShift) || blank($currentShift) || $debtShift === $currentShift) {
            return 0;
        }

        $currentStart = $record->scheduled_start_at->copy()->setTimezone(config('app.timezone'));
        $debtStart = $debt->scheduled_start_at->copy()->setTimezone(config('app.timezone'));
        $debtStartOnCurrentDate = $currentStart->copy()->setTimeFrom($debtStart);
        $creditStart = $record->check_in_time->greaterThan($currentStart) ? $record->check_in_time : $currentStart;

        return max(0, (int) $creditStart->diffInMinutes($debtStartOnCurrentDate));
    }
}
