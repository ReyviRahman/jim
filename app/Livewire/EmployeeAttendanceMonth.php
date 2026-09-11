<?php

namespace App\Livewire;

use App\EmployeeAttendanceStatus;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Url;

trait EmployeeAttendanceMonth
{
    use EmployeeAttendanceBulk, EmployeeAttendanceCells;

    #[Url(as: 'month')]
    public string $month = '';

    public function updatedMonth(): void
    {
        $this->normalizeAttendanceMonth();
        $this->cancelBulkAttendance();
    }

    private function normalizeAttendanceMonth(): void
    {
        if (! preg_match('/^[1-9][0-9]{3}-(0[1-9]|1[0-2])$/', $this->month)) {
            $this->month = now(config('app.timezone'))->format('Y-m');
        }
    }

    public function previousMonth(): void
    {
        $this->moveMonth(-1);
    }

    public function nextMonth(): void
    {
        $this->moveMonth(1);
    }

    public function currentMonth(): void
    {
        $this->month = now(config('app.timezone'))->format('Y-m');
        $this->updatedMonth();
    }

    private function moveMonth(int $offset): void
    {
        $this->updatedMonth();
        $this->month = Carbon::createFromFormat('!Y-m', $this->month)->addMonths($offset)->format('Y-m');
        $this->updatedMonth();
    }

    /** @return array<string, mixed> */
    private function employeeMonthData(): array
    {
        $this->normalizeAttendanceMonth();
        $start = Carbon::createFromFormat('!Y-m', $this->month, config('app.timezone'));
        $end = $start->copy()->endOfMonth();
        $employees = User::query()->forEmployeeAttendance($this->search)
            ->with(['assignedShift', 'employeeAttendances' => function (HasMany $query) use ($start, $end): void {
                $query->where(function (Builder $query) use ($start, $end): void {
                    $query->whereBetween('attendance_date', [$start->toDateString(), $end->toDateString()])
                        ->orWhere(fn (Builder $legacy) => $legacy->whereNull('attendance_date')->whereBetween('check_in_time', [$start, $end]))
                        ->orWhere(fn (Builder $legacy) => $legacy->whereNull('attendance_date')->whereNull('check_in_time')->whereBetween('check_out_time', [$start, $end]));
                })->orderBy('id');
            }])->orderBy('role')->orderBy('name')->orderBy('id')->get();

        $days = collect(range(1, $start->daysInMonth))->map(fn (int $day) => $start->copy()->day($day)->locale('id'));
        $cells = $employees->mapWithKeys(fn (User $user) => [$user->id => $user->employeeAttendances->groupBy(
            fn ($attendance) => ($attendance->attendance_date ?? $attendance->check_in_time ?? $attendance->check_out_time)->toDateString()
        )]);
        $totals = $employees->mapWithKeys(function (User $user): array {
            $counted = $user->employeeAttendances->filter(fn ($attendance) => $attendance->status !== EmployeeAttendanceStatus::Hadir || $attendance->check_in_time !== null);
            $counts = $counted->countBy(fn ($attendance) => $attendance->status->value);

            return [$user->id => [
                'hadir' => $counts->get('hadir', 0),
                'off' => $counts->get('off', 0),
                'izin' => $counts->get('izin', 0) + $counts->get('sakit', 0),
                'all' => $counted->count(),
            ]];
        });

        return [
            'monthLabel' => $start->locale('id')->translatedFormat('F Y'),
            'days' => $days,
            'employeeGroups' => $employees->groupBy('role'),
            'cells' => $cells,
            'totals' => $totals,
            'roleLabels' => Shift::ROLE_LABELS + ['head_coach' => 'Head Coach'],
            'legend' => $employees->flatMap->employeeAttendances->filter(fn ($row) => $row->status === EmployeeAttendanceStatus::Hadir)->unique(fn ($row) => $row->shift_code.'|'.$row->shift_name),
            'employeeCount' => $employees->count(),
        ];
    }
}
