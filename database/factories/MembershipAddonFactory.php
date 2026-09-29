<?php

namespace Database\Factories;

use App\Models\Membership;
use App\Models\MembershipAddon;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MembershipAddon>
 */
class MembershipAddonFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'membership_id' => fn () => Membership::create([
                'user_id' => User::factory()->create(['role' => 'member'])->id,
                'type' => 'pt', 'base_price' => 300000, 'price_paid' => 300000, 'total_paid' => 300000,
                'payment_status' => 'paid', 'status' => 'active', 'is_active' => true,
                'start_date' => today(), 'pt_end_date' => today()->addDays(29),
                'total_sessions' => 10, 'remaining_sessions' => 10,
            ])->id,
            'name' => 'Membership 1 Monthly Pass',
            'duration_months' => 1, 'duration_weeks' => 0, 'duration_days' => 0,
            'status' => 'pending', 'approval_status' => 'pending',
            'requested_by' => User::factory()->state(['role' => 'kasir_gym']),
            'requested_at' => now(),
        ];
    }
}
