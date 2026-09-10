<?php

namespace Database\Factories;

use App\Models\Shift;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Shift> */
class ShiftFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => 'ADM-PAGI',
            'name' => 'Admin pagi',
            'start_time' => '07:00:00',
            'end_time' => '15:00:00',
            'role' => 'admin',
        ];
    }
}
