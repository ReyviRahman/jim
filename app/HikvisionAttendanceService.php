<?php

namespace App;

use App\Models\Attendance;
use App\Models\DeviceEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class HikvisionAttendanceService
{
    public function __construct(private EmployeeAttendanceService $employeeAttendanceService) {}

    public function record(
        User $user,
        DeviceEvent $deviceEvent,
        Carbon $attendanceAt,
    ): bool {
        $user = User::query()
            ->whereKey($user->id)
            ->lockForUpdate()
            ->firstOrFail();

        if ($user->role !== 'member') {
            if ($deviceEvent->major_event_type !== 5 || $deviceEvent->sub_event_type !== 38) {
                return false;
            }

            try {
                return $this->employeeAttendanceService->record($user, $attendanceAt, $deviceEvent)->wasRecentlyCreated;
            } catch (ValidationException) {
                return false;
            }
        }

        $attendanceDate = $attendanceAt->toDateString();
        $dayStart = $attendanceAt->copy()->startOfDay();
        $dayEnd = $attendanceAt->copy()->endOfDay();

        $attendance = Attendance::query()
            ->whereBelongsTo($user)
            ->where(function (Builder $query) use ($attendanceDate, $dayStart, $dayEnd): void {
                $query
                    ->where('attendance_date', $attendanceDate)
                    ->orWhere(function (Builder $legacyQuery) use ($dayStart, $dayEnd): void {
                        $legacyQuery
                            ->whereNull('attendance_date')
                            ->where(function (Builder $timestampQuery) use ($dayStart, $dayEnd): void {
                                $timestampQuery
                                    ->whereBetween('check_in_time', [$dayStart, $dayEnd])
                                    ->orWhere(function (Builder $checkoutOnlyQuery) use ($dayStart, $dayEnd): void {
                                        $checkoutOnlyQuery
                                            ->whereNull('check_in_time')
                                            ->whereBetween('check_out_time', [$dayStart, $dayEnd]);
                                    });
                            });
                    });
            })
            ->orderByRaw('COALESCE(check_in_time, check_out_time, created_at)')
            ->orderBy('id')
            ->lockForUpdate()
            ->first();

        if ($attendance === null) {
            Attendance::create([
                'device_event_id' => $deviceEvent->id,
                'user_id' => $user->id,
                'nama_di_alat' => $deviceEvent->name,
                'membership_id' => null,
                'type' => null,
                'attendance_status' => null,
                'attendance_date' => $attendanceDate,
                'check_in_time' => $attendanceAt,
                'check_out_time' => null,
            ]);

            return true;
        }

        $attendance->update([
            'nama_di_alat' => $deviceEvent->name,
            'check_out_time' => $attendanceAt,
        ]);

        return false;
    }
}
