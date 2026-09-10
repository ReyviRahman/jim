<?php

namespace Database\Factories;

use App\Models\AttendanceEmployee;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttendanceEmployee>
 */
class AttendanceEmployeeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->state(['role' => 'admin']),
            'attendance_date' => today()->toDateString(),
            'check_in_time' => today()->setTime(8, 0),
            'check_out_time' => null,
            'shift_code' => 'P',
            'shift_name' => 'Pagi',
            'shift_role' => 'admin',
            'shift_start_time' => '08:00:00',
            'shift_end_time' => '16:00:00',
            'scheduled_start_at' => today()->setTime(8, 0),
            'scheduled_end_at' => today()->setTime(16, 0),
            'checkout_deadline_at' => today()->addDay()->setTime(8, 0),
        ];
    }
}
