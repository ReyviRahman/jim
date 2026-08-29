<?php

namespace App;

use App\Models\Attendance;
use App\Models\DeviceEvent;
use App\Models\User;
use Illuminate\Support\Carbon;

class HikvisionAttendanceService
{
    public function record(
        User $user,
        DeviceEvent $deviceEvent,
        string $attendanceStatus,
        Carbon $accessedAt,
    ): bool {
        User::query()
            ->whereKey($user->id)
            ->lockForUpdate()
            ->firstOrFail();

        $localAccessedAt = $accessedAt
            ->copy()
            ->setTimezone((string) config('app.timezone'));
        $attendanceDate = $localAccessedAt->toDateString();

        $attendance = Attendance::query()
            ->whereBelongsTo($user)
            ->where('attendance_date', $attendanceDate)
            ->lockForUpdate()
            ->first();

        if ($attendance === null) {
            Attendance::create([
                'device_event_id' => $deviceEvent->id,
                'user_id' => $user->id,
                'membership_id' => null,
                'type' => null,
                'attendance_status' => null,
                'attendance_date' => $attendanceDate,
                'check_in_time' => $attendanceStatus === 'checkIn' ? $localAccessedAt : null,
                'check_out_time' => $attendanceStatus === 'checkOut' ? $localAccessedAt : null,
            ]);

            return true;
        }

        if ($attendanceStatus === 'checkIn') {
            if ($attendance->check_in_time !== null) {
                return false;
            }

            $attendance->update(['check_in_time' => $localAccessedAt]);

            return false;
        }

        $checkOutWasFilled = $attendance->check_out_time === null;

        if ($checkOutWasFilled || $localAccessedAt->greaterThan($attendance->check_out_time)) {
            $attendance->update(['check_out_time' => $localAccessedAt]);
        }

        return false;
    }
}
