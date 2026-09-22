<?php

namespace App\Actions;

use App\MemberPtSchedule;
use App\Models\PtBooking;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CreateMemberPtBooking
{
    public function __construct(private MemberPtSchedule $schedule) {}

    public function execute(User $member, int $membershipId, string $date, string $time): PtBooking
    {
        abort_unless($member->role === 'member', 403);

        Validator::make(['bookingDate' => $date, 'bookingTime' => $time], [
            'bookingDate' => ['required', 'date_format:Y-m-d'],
            'bookingTime' => ['required', Rule::in(array_map(fn (int $hour): string => sprintf('%02d:00', $hour), range(7, 22)))],
        ], [
            'bookingDate.*' => 'Tanggal booking tidak valid.',
            'bookingTime.*' => 'Pilih slot setiap jam mulai 07:00 sampai 22:00.',
        ])->validate();

        return DB::transaction(function () use ($member, $membershipId, $date, $time): PtBooking {
            $membership = $this->schedule->memberships($member)->lockForUpdate()->find($membershipId);
            abort_unless($membership, 403);

            $coach = User::query()->lockForUpdate()->find($membership->pt_id);
            $membership->setRelation('personalTrainer', $coach);
            $reserved = $this->schedule->reservations($membership)->lockForUpdate()->get(['id'])->count();
            $start = Carbon::createFromFormat('!Y-m-d H:i', $date.' '.$time, config('app.timezone'));
            $reason = $this->schedule->unavailableReason($membership, $reserved)
                ?? $this->schedule->dateUnavailableReason($membership, $start);

            if ($reason !== null) {
                throw ValidationException::withMessages(['booking' => $reason]);
            }

            if ($this->schedule->overlappingBookings($coach->id, $start)->lockForUpdate()->first(['id'])) {
                throw ValidationException::withMessages(['booking' => 'Slot ini sudah terbooking. Silakan pilih jam lain.']);
            }

            return PtBooking::create([
                'membership_id' => $membership->id,
                'member_id' => $membership->user_id,
                'pt_id' => $coach->id,
                'booking_date' => $date,
                'booking_time' => $time.':00',
                'status' => 'pending',
                'type' => 'fleksibel',
                'attendance' => 'not_yet',
                'is_free' => false,
            ]);
        }, attempts: 3);
    }
}
