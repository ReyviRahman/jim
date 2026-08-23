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

        $this->createAttendance($headCoach);

        $this->actingAs($admin);

        Livewire::test('pages::dashboard.admin.absensi.index')
            ->assertSee('Nama User')
            ->assertSee('Role User')
            ->assertSee('Head Coach')
            ->assertDontSee('Tipe Kedatangan')
            ->assertDontSee('Detail Paket');
    }

    public function test_employee_attendance_page_only_shows_non_member_attendances(): void
    {
        $admin = $this->createUser('admin');
        $member = $this->createUser('member', 'Member Attendance');
        $headCoach = $this->createUser('head_coach', 'Employee Attendance');

        $this->createAttendance($member);
        $this->createAttendance($headCoach);

        $this->actingAs($admin)
            ->get(route('admin.absensi-karyawan.index'))
            ->assertOk()
            ->assertSee('Data Absensi Karyawan &amp; Scanner', false)
            ->assertSee('Employee Attendance')
            ->assertDontSee('Member Attendance')
            ->assertSee('Hasil QR muncul disini')
            ->assertSee('Pilih Rentang Tanggal')
            ->assertSee('Nama User')
            ->assertSee('Role User')
            ->assertSee('Waktu Check-In')
            ->assertDontSee('Semua Role');
    }

    public function test_general_attendance_page_keeps_role_filter_and_all_attendances(): void
    {
        $admin = $this->createUser('admin');
        $member = $this->createUser('member', 'General Member Attendance');
        $headCoach = $this->createUser('head_coach', 'General Employee Attendance');

        $this->createAttendance($member);
        $this->createAttendance($headCoach);

        $this->actingAs($admin)
            ->get(route('admin.absensi.index'))
            ->assertOk()
            ->assertSee('Semua Role')
            ->assertSee('General Member Attendance')
            ->assertSee('General Employee Attendance');
    }

    public function test_employee_attendance_navigation_is_below_general_attendance_navigation(): void
    {
        $admin = $this->createUser('admin');

        $this->actingAs($admin)
            ->get(route('admin.absensi-karyawan.index'))
            ->assertOk()
            ->assertSeeInOrder([
                route('admin.absensi.index'),
                'class="text-white flex items-center px-2 py-1.5 rounded-md hover:bg-brand hover:text-[#34342F] group transition-colors"',
                '<span class="ms-3">Absensi</span>',
                route('admin.absensi-karyawan.index'),
                'class="text-[#34342F] bg-brand flex items-center px-2 py-1.5 rounded-md hover:bg-brand hover:text-[#34342F] group transition-colors"',
                '<span class="ms-3">Absensi Karyawan</span>',
            ], false);
    }

    private function createUser(string $role, ?string $name = null): User
    {
        return User::factory()->create([
            'name' => $name ?? fake()->name(),
            'age' => 30,
            'gender' => 'Laki-laki',
            'phone' => fake()->unique()->numerify('08##########'),
            'role' => $role,
        ]);
    }

    private function createAttendance(User $user): Attendance
    {
        return Attendance::create([
            'user_id' => $user->id,
            'membership_id' => null,
            'type' => null,
            'attendance_status' => 'checkIn',
            'check_in_time' => now(),
        ]);
    }
}
