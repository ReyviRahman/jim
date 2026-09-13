<?php

namespace Database\Factories;

use App\MembershipWaiverTerms;
use App\Models\Membership;
use App\Models\MembershipWaiver;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MembershipWaiver> */
class MembershipWaiverFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'membership_id' => fn (): int => Membership::create([
                'user_id' => User::factory()->create()->id,
                'type' => 'membership',
                'base_price' => 300000,
                'price_paid' => 300000,
                'total_paid' => 0,
                'start_date' => today(),
            ])->id,
            'user_id' => fn (array $attributes): int => Membership::findOrFail($attributes['membership_id'])->user_id,
            'member_name' => fake()->name(),
            'accepted' => false,
            'signature_path' => null,
            'recorded_at' => now(),
            'admin_id' => null,
            'terms_snapshot' => MembershipWaiverTerms::snapshot(),
            'consent_label' => MembershipWaiverTerms::CONSENT_LABEL,
        ];
    }
}
