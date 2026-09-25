<?php

namespace Database\Factories;

use App\Models\MembershipOperationalRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MembershipOperationalRequest>
 */
class MembershipOperationalRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'submission_token' => fake()->uuid(),
            'requested_by' => User::factory(),
            'requested_by_name' => fake()->name(),
            'status' => 'pending',
            'reason' => 'Operasional membership',
            'requested_at' => now(),
            'snapshot' => [],
            'documents' => [],
        ];
    }
}
