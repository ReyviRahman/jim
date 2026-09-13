<?php

namespace Database\Seeders;

use App\MembershipWaiverTerms;
use App\Models\Membership;
use Illuminate\Database\Seeder;

class MembershipWaiverSeeder extends Seeder
{
    public function run(): void
    {
        Membership::with(['user', 'members'])->each(function (Membership $membership): void {
            $members = $membership->members->prepend($membership->user)->filter()->unique('id');
            foreach ($members as $member) {
                $membership->waivers()->firstOrCreate(['user_id' => $member->id], [
                    'member_name' => $member->name,
                    'accepted' => false,
                    'recorded_at' => now(),
                    'terms_snapshot' => MembershipWaiverTerms::snapshot(),
                    'consent_label' => MembershipWaiverTerms::CONSENT_LABEL,
                ]);
            }
        });
    }
}
