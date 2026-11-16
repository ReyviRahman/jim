<?php

namespace App\Actions;

use App\MemberPtSchedule;
use App\Models\Membership;
use App\Models\PtBooking;
use App\Models\User;
use App\PtStudioBooking;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

class CreateKeepPtBookings
{
    public function __construct(private MemberPtSchedule $schedule) {}

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
        string $studioType,
    ): array {
        return app(PtStudioBooking::class)->transaction($studioType, function () use ($membershipId, $ptId, $weekStart, $dayTimes, $status, $studioType): array {
            $membership = Membership::query()
                ->bookableByAdmin()
                ->lockForUpdate()
                ->find($membershipId);

            if (! $membership) {
                return $this->result(stopReason: 'invalid_membership');
            }

            $orderedDayTimes = collect(self::DAY_OFFSETS)
                ->filter(fn (int $offset, string $day): bool => isset($dayTimes[$day]))
                ->mapWithKeys(fn (int $offset, string $day): array => [$day => $dayTimes[$day]])
                ->all();

            if ($orderedDayTimes === [] || array_diff($orderedDayTimes, PtBooking::hourlySlots()) !== []) {
                return $this->result(stopReason: 'invalid_pattern');
            }

            User::query()->lockForUpdate()->findOrFail($ptId, ['id']);

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
            $expiresAt = $membership->hasRenewalSuccessor() ? null : $membership->pt_end_date?->copy()->endOfDay();
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

                    $conflict = $this->schedule->overlappingBookings($ptId, $candidate)
                        ->orderBy('booking_time')->lockForUpdate()->first(['booking_time']);

                    if ($conflict) {
                        throw ValidationException::withMessages([
                            'insertDayTimes.'.$day => 'Jadwal Keep '.$candidate->format('d/m/Y H:i').' bentrok dengan sesi coach pukul '.$conflict->booking_time->format('H:i').'. Pilih jam lain.',
                        ]);
                    }

                    app(PtStudioBooking::class)->validate($membership, $studioType, $candidate);

                    PtBooking::create([
                        'studio_type' => $studioType,
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
        });
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
