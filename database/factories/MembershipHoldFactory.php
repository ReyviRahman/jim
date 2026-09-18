<?php

namespace Database\Factories;

use App\Models\Membership;
use App\Models\MembershipHold;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MembershipHold>
 */
class MembershipHoldFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'membership_id' => fn (): int => Membership::create([
                'user_id' => User::factory()->create(['role' => 'member'])->id,
                'type' => 'pt',
                'base_price' => 1000000,
                'price_paid' => 1000000,
                'total_paid' => 1000000,
                'payment_status' => 'paid',
                'total_sessions' => 10,
                'remaining_sessions' => 5,
                'pt_end_date' => today()->addMonthNoOverflow(),
                'status' => 'active',
            ])->id,
            'created_by' => User::factory()->state(['role' => 'admin']),
            'submission_token' => fn (): string => (string) Str::uuid(),
            'months' => 1,
            'monthly_price' => null,
            'total_amount' => 150000,
            'previous_end_date' => today(),
            'new_end_date' => today()->addMonthNoOverflow(),
        ];
    }
}
