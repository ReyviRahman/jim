<?php

namespace App\Actions;

use App\MemberPtSchedule;
use App\Models\PtBooking;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CancelMemberPtBooking
{
    public function __construct(private MemberPtSchedule $schedule) {}

    public function execute(User $member, int $bookingId, string $reason): PtBooking
    {
        abort_unless($member->role === 'member', 403);
        $reason = trim($reason);
        Validator::make(['cancelReason' => $reason], [
            'cancelReason' => ['required', 'string', 'min:5', 'max:500'],
        ], [
            'cancelReason.required' => 'Alasan pembatalan wajib diisi.',
            'cancelReason.min' => 'Alasan pembatalan minimal 5 karakter.',
            'cancelReason.max' => 'Alasan pembatalan maksimal 500 karakter.',
        ])->validate();

        return DB::transaction(function () use ($member, $bookingId, $reason): PtBooking {
            $candidate = PtBooking::query()
                ->whereIn('membership_id', $this->schedule->memberships($member)->select('id'))
                ->find($bookingId);
            abort_unless($candidate, 404);

            $membership = $this->schedule->memberships($member)->lockForUpdate()->find($candidate->membership_id);
            abort_unless($membership, 404);
            User::query()->whereKey($candidate->pt_id)->lockForUpdate()->firstOrFail();
            $booking = PtBooking::query()->where('membership_id', $membership->id)->lockForUpdate()->find($bookingId);
            abort_unless($booking, 404);

            if ($booking->isCancelled() || $booking->isCancellationPending()) {
                return $booking;
            }

            if ($message = $this->schedule->cancellationUnavailableReason($booking)) {
                throw ValidationException::withMessages(['cancelReason' => $message]);
            }

            $requiresApproval = $booking->isApproved();
            $booking->update([
                'status' => $requiresApproval ? 'approved' : 'cancelled',
                'cancelled_by' => $member->id,
                'cancelled_at' => $requiresApproval ? null : now(),
                'cancellation_reason' => $reason,
                'cancellation_requested_at' => $requiresApproval ? now() : null,
            ]);

            return $booking;
        }, attempts: 3);
    }
}
