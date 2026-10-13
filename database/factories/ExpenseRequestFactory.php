<?php

namespace Database\Factories;

use App\Models\ExpenseRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExpenseRequest>
 */
class ExpenseRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'source' => 'gym',
            'requested_by' => User::factory()->state(['role' => 'kasir_gym']),
            'requested_by_name' => fake()->name(),
            'shift' => 'pagi',
            'description' => fake()->sentence(),
            'amount' => 15000,
            'expense_date' => today()->toDateString(),
            'status' => 'pending',
            'requested_at' => now(),
        ];
    }
}
