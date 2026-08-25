<?php

namespace App\Actions;

use App\Models\Membership;
use App\Models\PtBooking;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class CreateKeepPtBookings
{
    private const DAY_OFFSETS = [
        'senin' => 0,
        'selasa' => 1,
        'rabu' => 2,
        'kamis' => 3,
        'jumat' => 4,
        'sabtu' => 5,
        'minggu' => 6,
    ];

    /**
     * @param  array<string, string>  $dayTimes
     * @return array{created_count: int, capacity: int, stop_reason: null|'invalid_membership'|'invalid_pattern'|'no_capacity'|'pt_end_date'}
     */
    public function execute(
        int $membershipId,
        int $ptId,
        CarbonInterface $weekStart,
        array $dayTimes,
        string $status,
    ): array {
        return DB::transaction(function () use ($membershipId, $ptId, $weekStart, $dayTimes, $status): array {
            $membership = Membership::query()
                ->lockForUpdate()
                ->find($membershipId);

            if (! $membership || ! $membership->is_active || $membership->status !== 'active' || ($membership->remaining_sessions ?? 0) <= 0) {
                return $this->result(stopReason: 'invalid_membership');
            }

            $orderedDayTimes = collect(self::DAY_OFFSETS)
                ->filter(fn (int $offset, string $day): bool => isset($dayTimes[$day]))
                ->mapWithKeys(fn (int $offset, string $day): array => [$day => $dayTimes[$day]])
                ->all();

            if ($orderedDayTimes === []) {
                return $this->result(stopReason: 'invalid_pattern');
            }

            $activeBookings = PtBooking::query()
                ->whereBelongsTo($membership)
                ->whereIn('status', ['pending', 'approved'])
                ->where('attendance', 'not_yet')
                ->get(['booking_date', 'booking_time', 'is_free']);

            $reservedSessions = $activeBookings
                ->where('is_free', false)
                ->count();
            $capacity = max(0, (int) $membership->remaining_sessions - $reservedSessions);

            if ($capacity === 0) {
                return $this->result(capacity: 0, stopReason: 'no_capacity');
            }

            $activeBookingKeys = $activeBookings
                ->mapWithKeys(fn (PtBooking $booking): array => [$this->bookingKey(
                    $booking->booking_date->toDateString(),
                    $booking->booking_time->format('H:i:s'),
                ) => true])
                ->all();

            $currentWeekStart = Carbon::parse($weekStart->toDateString(), config('app.timezone'))->startOfDay();
            $expiresAt = $membership->pt_end_date?->copy()->endOfDay();
            $createdCount = 0;
            $stopReason = null;

            while ($createdCount < $capacity) {
                foreach ($orderedDayTimes as $day => $time) {
                    $candidate = Carbon::createFromFormat(
                        'Y-m-d H:i',
                        $currentWeekStart->copy()->addDays(self::DAY_OFFSETS[$day])->toDateString().' '.$time,
                        config('app.timezone'),
                    );

                    if ($expiresAt && $candidate->isAfter($expiresAt)) {
                        $stopReason = 'pt_end_date';

                        break 2;
                    }

                    $bookingKey = $this->bookingKey($candidate->toDateString(), $candidate->format('H:i:s'));

                    if (isset($activeBookingKeys[$bookingKey])) {
                        continue;
                    }

                    PtBooking::create([
                        'membership_id' => $membership->id,
                        'member_id' => $membership->user_id,
                        'pt_id' => $ptId,
                        'booking_date' => $candidate->toDateString(),
                        'booking_time' => $candidate->format('H:i:s'),
                        'status' => $status,
                        'type' => 'keep',
                        'attendance' => 'not_yet',
                        'is_free' => false,
                    ]);

                    $activeBookingKeys[$bookingKey] = true;
                    $createdCount++;

                    if ($createdCount === $capacity) {
                        break 2;
                    }
                }

                $currentWeekStart->addWeek();
            }

            return $this->result($createdCount, $capacity, $stopReason);
        }, attempts: 3);
    }

    private function bookingKey(string $date, string $time): string
    {
        return $date.'|'.$time;
    }

    /**
     * @return array{created_count: int, capacity: int, stop_reason: null|'invalid_membership'|'invalid_pattern'|'no_capacity'|'pt_end_date'}
     */
    private function result(int $createdCount = 0, int $capacity = 0, ?string $stopReason = null): array
    {
        return [
            'created_count' => $createdCount,
            'capacity' => $capacity,
            'stop_reason' => $stopReason,
        ];
    }
}
