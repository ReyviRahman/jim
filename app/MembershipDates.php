<?php

namespace App;

use App\Models\GymPackage;
use App\Models\Membership;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

final class MembershipDates
{
    /** @return array{membership_end_date: ?string, pt_end_date: ?string} */
    public function endDates(string $startDate, ?GymPackage $gym, ?GymPackage $pt): array
    {
        $start = CarbonImmutable::parse($startDate)->startOfDay();
        $dates = ['membership_end_date' => null, 'pt_end_date' => null];

        foreach (['gym_package_id' => $gym, 'pt_package_id' => $pt] as $field => $package) {
            if ($package === null) {
                continue;
            }
            if ($package->durationInDays() < 1) {
                throw ValidationException::withMessages([$field => 'Durasi paket belum diisi. Lengkapi durasi di master paket.']);
            }
            $dates[$field === 'gym_package_id' ? 'membership_end_date' : 'pt_end_date'] = $start->addDays($package->durationInDays() - 1)->toDateString();
        }

        return $dates;
    }

    public function renewalStartDate(Membership $membership): string
    {
        $dates = match ($membership->type) {
            'pt' => [$membership->pt_end_date],
            'bundle_pt_membership' => [$membership->membership_end_date, $membership->pt_end_date],
            default => [$membership->membership_end_date],
        };
        $reference = collect($dates)->filter()->max();
        $today = CarbonImmutable::today();

        if ($reference === null || $today->gt($reference->copy()->startOfDay()->addDays(3))) {
            return $today->toDateString();
        }

        return $reference->toDateString();
    }
}
