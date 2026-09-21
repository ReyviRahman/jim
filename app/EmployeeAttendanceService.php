<?php

namespace App;

use App\Models\AttendanceEmployee;
use App\Models\DeviceEvent;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EmployeeAttendanceService
{
    public function record(User $user, Carbon $receivedAt, ?DeviceEvent $deviceEvent = null): AttendanceEmployee
    {
        $receivedAt = $receivedAt->copy()->setTimezone(config('app.timezone'))->startOfSecond();

        return DB::transaction(function () use ($user, $receivedAt, $deviceEvent): AttendanceEmployee {
            $employee = User::query()->lockForUpdate()->findOrFail($user->id);
            if (! array_key_exists($employee->role, Shift::ROLE_LABELS)) {
                throw ValidationException::withMessages(['attendance' => 'Akun ini bukan karyawan.']);
            }

            if ($deviceEvent !== null) {
                $duplicate = AttendanceEmployee::query()->where('device_event_id', $deviceEvent->id)->first();
                if ($duplicate !== null) {
                    $this->ensurePresent($duplicate);

                    return $duplicate;
                }
            }

            $attendance = $employee->employeeAttendances()
                ->where('attendance_date', $receivedAt->toDateString())
                ->lockForUpdate()->first();

            if ($attendance !== null) {
                $this->ensurePresent($attendance);
            }

            if ($attendance?->check_in_time !== null) {
                $checkoutStart = $attendance->check_in_time->min($attendance->scheduled_start_at);
                if ($receivedAt->lessThan($checkoutStart) || $receivedAt->greaterThanOrEqualTo($attendance->checkout_deadline_at)) {
                    throw ValidationException::withMessages(['attendance' => 'Presensi untuk tanggal shift ini sudah tercatat.']);
                }

                $lastScan = $attendance->check_out_time ?? $attendance->check_in_time;
                if ($receivedAt->greaterThan($lastScan)) {
                    $attendance->update(['check_out_time' => $receivedAt]);
                }

                return $attendance;
            }

            ['shift' => $shift, 'start' => $start] = $this->nearestShift($employee, $receivedAt);
            $end = $start->copy()->setTimeFromTimeString($shift->end_time);
            if ($end->lessThan($start)) {
                $end->addDay();
            }

            $attendance ??= $employee->employeeAttendances()->make();
            $attendance->fill([
                'attendance_date' => $receivedAt->toDateString(),
                'status' => EmployeeAttendanceStatus::Hadir,
                'check_in_time' => $receivedAt,
                'shift_code' => $shift->code,
                'shift_name' => $shift->name,
                'shift_role' => $shift->role,
                'shift_start_time' => $shift->start_time,
                'shift_end_time' => $shift->end_time,
                'scheduled_start_at' => $start,
                'scheduled_end_at' => $end,
                'checkout_deadline_at' => $start->copy()->addDay(),
            ]);
            if ($deviceEvent !== null) {
                $attendance->device_event_id = $deviceEvent->id;
                $attendance->nama_di_alat = $deviceEvent->name;
            }
            $attendance->save();

            return $attendance;
        }, attempts: 3);
    }

    /** @return array{shift: Shift, start: Carbon} */
    private function nearestShift(User $employee, Carbon $receivedAt): array
    {
        $nearest = null;
        $nearestDistance = PHP_INT_MAX;
        $shifts = Shift::query()->forRole($employee->role)
            ->whereColumn('start_time', '!=', 'end_time')->orderBy('id')->get();

        foreach ($shifts as $shift) {
            foreach ([-1, 0, 1] as $dayOffset) {
                $start = $receivedAt->copy()->addDays($dayOffset)->setTimeFromTimeString($shift->start_time);
                $distance = abs($start->getTimestamp() - $receivedAt->getTimestamp());
                if ($distance < $nearestDistance || ($distance === $nearestDistance && $start->lessThan($nearest['start']))) {
                    $nearest = ['shift' => $shift, 'start' => $start];
                    $nearestDistance = $distance;
                }
            }
        }

        if ($nearest === null) {
            throw ValidationException::withMessages(['attendance' => 'Tidak ada shift valid untuk role karyawan ini.']);
        }

        return $nearest;
    }

    private function ensurePresent(AttendanceEmployee $attendance): void
    {
        if ($attendance->status !== EmployeeAttendanceStatus::Hadir) {
            throw ValidationException::withMessages([
                'attendance' => 'Presensi tanggal '.$attendance->attendance_date->format('d-m-Y').' berstatus '.$attendance->status->label().'. Hubungi Admin untuk mengubah status sebelum melakukan scan.',
            ]);
        }
    }
}
