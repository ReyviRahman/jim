<?php

namespace App;

use App\Models\Membership;
use App\Models\PtBooking;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

class MemberPtSchedule
{
    /** @return Builder<Membership> */
    public function memberships(User $member): Builder
    {
        return Membership::query()->where('type', 'pt')
            ->where(function (Builder $query) use ($member): void {
                $query->where('user_id', $member->id)
                    ->orWhereHas('members', fn (Builder $members): Builder => $members->whereKey($member->id));
            });
    }

    /** @return Builder<PtBooking> */
    public function reservations(Membership $membership): Builder
    {
        return $membership->ptBookings()->getQuery()
            ->where('status', 'pending')
            ->where('attendance', 'not_yet')->where('is_free', false);
    }

    public function unavailableReason(?Membership $membership, int $reservedSessions): ?string
    {
        return match (true) {
            $membership === null => 'Tidak ada membership PT yang tersedia.',
            ! $membership->is_active || $membership->status !== 'active' => 'Membership PT tidak aktif.',
            $membership->pt_id === null || $membership->personalTrainer === null => 'Coach belum ditentukan untuk paket ini.',
            $membership->pt_end_date !== null && $membership->pt_end_date->lt(today()) => 'Masa berlaku paket PT sudah berakhir.',
            (int) $membership->remaining_sessions <= $reservedSessions => 'Sisa sesi sudah habis atau seluruhnya ditahan oleh booking pending.',
            default => null,
        };
    }

    public function dateUnavailableReason(Membership $membership, CarbonInterface $start): ?string
    {
        return match (true) {
            $start->lte(now()) => 'Waktu sesi sudah lewat.',
            ! $start->isSameDay(today(config('app.timezone'))) && ! $start->isSameDay(today(config('app.timezone'))->addDay()) => 'Booking hanya bisa dibuat untuk hari ini atau besok.',
            $membership->start_date !== null && $start->toDateString() < $membership->start_date->toDateString() => 'Paket PT belum dimulai pada tanggal ini.',
            $membership->pt_end_date !== null && $start->toDateString() > $membership->pt_end_date->toDateString() => 'Tanggal sesi melewati masa berlaku paket PT.',
            default => null,
        };
    }

    /** @return Builder<PtBooking> */
    public function overlappingBookings(int $coachId, CarbonInterface $start): Builder
    {
        return PtBooking::query()->where('pt_id', $coachId)
            ->whereIn('status', ['pending', 'approved'])
            ->whereDate('booking_date', $start->toDateString())
            ->where('booking_time', '>', $start->copy()->subHour()->format('H:i:s'))
            ->where('booking_time', '<', $start->copy()->addHour()->format('H:i:s'));
    }

    public function cancellationUnavailableReason(PtBooking $booking): ?string
    {
        return match (true) {
            ! in_array($booking->status, ['pending', 'approved'], true) => 'Booking ini tidak dapat dibatalkan.',
            $booking->isCancellationPending() => 'Permintaan pembatalan sedang diproses.',
            $booking->attendance !== 'not_yet' => 'Booking yang sudah diabsen tidak dapat dibatalkan.',
            $booking->booking_date->copy()->setTimeFrom($booking->booking_time)->lte(now()) => 'Sesi yang sudah dimulai tidak dapat dibatalkan.',
            $booking->booking_date->copy()->setTimeFrom($booking->booking_time)->lte(now()->addHours(3)) => 'Pembatalan tidak tersedia mulai 3 jam sebelum jadwal sesi.',
            default => null,
        };
    }
}
