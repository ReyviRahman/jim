<?php

namespace App;

use App\Models\Membership;
use App\Models\PtBooking;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PtStudioBooking
{
    public function transaction(string $studioType, Closure $callback): mixed
    {
        $connection = DB::connection();
        $lock = 'pt-studio:'.sha1($connection->getDatabaseName());
        $private = $studioType === 'private_studio';
        if ($private && (int) $connection->selectOne('SELECT GET_LOCK(?, 10) AS acquired', [$lock], false)->acquired !== 1) {
            throw ValidationException::withMessages(['studioType' => 'Studio sedang diproses. Silakan coba kembali.']);
        }
        try {
            return $connection->transaction($callback, 3);
        } finally {
            if ($private) {
                $connection->selectOne('SELECT RELEASE_LOCK(?) AS released', [$lock], false);
            }
        }
    }

    public function validate(Membership $membership, string $studioType, CarbonInterface $start, ?int $excludeBookingId = null): void
    {
        if (! in_array($studioType, ['private_studio', 'regular'], true)) {
            throw ValidationException::withMessages(['studioType' => 'Pilih Private Studio atau Regular.']);
        }
        if ($studioType === 'regular') {
            return;
        }
        if (! $membership->hasNormalPrice()) {
            throw ValidationException::withMessages(['studioType' => 'Private Studio hanya tersedia untuk membership Harga Normal.']);
        }
        $end = $start->copy()->addHour();
        $events = [[$start->timestamp, 1], [$end->timestamp, -1]];
        $bookings = PtBooking::query()->where('studio_type', 'private_studio')
            ->when($excludeBookingId !== null, fn ($query) => $query->whereKeyNot($excludeBookingId))
            ->whereIn('status', ['pending', 'approved'])
            ->whereBetween('booking_date', [$start->copy()->subHour()->toDateString(), $end->toDateString()])
            ->get(['booking_date', 'booking_time']);
        foreach ($bookings as $booking) {
            $otherStart = $booking->booking_date->copy()->setTimeFrom($booking->booking_time);
            $otherEnd = $otherStart->copy()->addHour();
            if ($otherStart->lt($end) && $otherEnd->gt($start)) {
                $events[] = [max($start->timestamp, $otherStart->timestamp), 1];
                $events[] = [min($end->timestamp, $otherEnd->timestamp), -1];
            }
        }
        usort($events, fn (array $a, array $b): int => ($a[0] <=> $b[0]) ?: ($a[1] <=> $b[1]));
        $occupied = 0;
        foreach ($events as [$time, $change]) {
            $occupied += $change;
            if ($occupied > 2) {
                throw ValidationException::withMessages(['studioType' => 'Private Studio penuh pada '.$start->format('d/m/Y H:i').'. Maksimal 2 booking bersamaan.']);
            }
        }
    }
}
