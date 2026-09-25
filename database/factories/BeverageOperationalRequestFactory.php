<?php

namespace Database\Factories;

use App\Models\BeverageOperationalRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BeverageOperationalRequest>
 */
class BeverageOperationalRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'requested_by' => User::factory(),
            'nama_staff' => fake()->name(),
            'shift' => 'Pagi',
            'reason' => 'Kebutuhan operasional',
            'status' => 'pending',
            'requested_at' => now(),
            'total' => 0,
            'deposit_amount' => 0,
        ];
    }
}
