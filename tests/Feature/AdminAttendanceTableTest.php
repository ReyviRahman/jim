<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminAttendanceTableTest extends TestCase
{
    use RefreshDatabase;

    public function test_attendance_table_shows_user_role_without_arrival_type_or_package_details(): void
    {
        $admin = $this->createUser('admin');
        $headCoach = $this->createUser('head_coach');

        Attendance::create([
            'user_id' => $headCoach->id,
            'membership_id' => null,
            'type' => null,
            'attendance_status' => 'checkIn',
            'check_in_time' => now(),
        ]);

        $this->actingAs($admin);

        Livewire::test('pages::dashboard.admin.absensi.index')
            ->assertSee('Nama User')
            ->assertSee('Role User')
            ->assertSee('Head Coach')
            ->assertDontSee('Tipe Kedatangan')
            ->assertDontSee('Detail Paket');
    }

    private function createUser(string $role): User
    {
        return User::factory()->create([
            'age' => 30,
            'gender' => 'Laki-laki',
            'phone' => fake()->unique()->numerify('08##########'),
            'role' => $role,
        ]);
    }
}
