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
                ->where('status', EmployeeAttendanceStatus::Hadir)
                ->where('scheduled_start_at', '<=', $receivedAt)
                ->where('checkout_deadline_at', '>', $receivedAt)
                ->latest('scheduled_start_at')->lockForUpdate()->first();

            if ($attendance !== null) {
                $lastScan = $attendance->check_out_time ?? $attendance->check_in_time;
                if ($receivedAt->greaterThan($lastScan)) {
                    $attendance->update(['check_out_time' => $receivedAt]);
                }

                return $attendance;
            }

            $shift = $employee->assignedShift;
            if ($shift === null || $shift->role !== $employee->role) {
                throw ValidationException::withMessages(['attendance' => 'Shift karyawan belum diatur atau tidak sesuai role.']);
            }

            $start = $receivedAt->copy()->setTimeFromTimeString($shift->start_time);
            $end = $receivedAt->copy()->setTimeFromTimeString($shift->end_time);
            if ($start->equalTo($end)) {
                throw ValidationException::withMessages(['attendance' => 'Jam mulai dan selesai shift tidak boleh sama.']);
            }
            if ($end->lessThan($start)) {
                if ($receivedAt->lessThan($start)) {
                    $start->subDay();
                } else {
                    $end->addDay();
                }
            }

            if (! $receivedAt->betweenIncluded($start, $end)) {
                throw ValidationException::withMessages(['attendance' => 'Check-in hanya diperbolehkan pada jam shift '.$shift->name.' ('.$shift->start_time.'–'.$shift->end_time.').']);
            }

            $existing = $employee->employeeAttendances()->where('attendance_date', $start->toDateString())->lockForUpdate()->first();
            if ($existing !== null) {
                $this->ensurePresent($existing);

                throw ValidationException::withMessages(['attendance' => 'Presensi untuk tanggal shift ini sudah tercatat.']);
            }

            return $employee->employeeAttendances()->create([
                'device_event_id' => $deviceEvent?->id,
                'nama_di_alat' => $deviceEvent?->name,
                'attendance_date' => $start->toDateString(),
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
        }, attempts: 3);
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
