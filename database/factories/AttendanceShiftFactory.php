<?php

namespace Database\Factories;

use App\Models\AttendanceShift;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttendanceShift>
 */
class AttendanceShiftFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'ADM-PAGI',
            'name' => 'Admin pagi',
            'start_time' => '07:00:00',
            'end_time' => '15:00:00',
        ];
    }
}
