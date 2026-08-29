<?php

namespace App;

use App\Models\Attendance;
use App\Models\DeviceEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class HikvisionAttendanceService
{
    public function record(
        User $user,
        DeviceEvent $deviceEvent,
        Carbon $receivedAt,
    ): bool {
        User::query()
            ->whereKey($user->id)
            ->lockForUpdate()
            ->firstOrFail();

        $attendanceDate = $receivedAt->toDateString();
        $dayStart = $receivedAt->copy()->startOfDay();
        $dayEnd = $receivedAt->copy()->endOfDay();

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
                'membership_id' => null,
                'type' => null,
                'attendance_status' => null,
                'attendance_date' => $attendanceDate,
                'check_in_time' => $receivedAt,
                'check_out_time' => null,
            ]);

            return true;
        }

        $attendance->update(['check_out_time' => $receivedAt]);

        return false;
    }
}
