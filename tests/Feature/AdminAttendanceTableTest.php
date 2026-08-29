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

        $this->createAttendance($member, [
            'attendance_date' => '2026-08-29',
            'check_in_time' => '2026-08-29 09:00:00',
            'check_out_time' => '2026-08-29 18:00:00',
        ]);
        $this->createAttendance($headCoach, [
            'attendance_date' => '2026-08-29',
            'check_in_time' => '2026-08-29 08:05:00',
            'check_out_time' => '2026-08-29 17:45:00',
        ]);

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
            ->assertSee('Waktu Check-Out')
            ->assertSee('29 Aug 2026')
            ->assertSee('08:05')
            ->assertSee('17:45')
            ->assertDontSee('Semua Role');
    }

    public function test_general_attendance_page_keeps_role_filter_and_shows_check_out_time(): void
    {
        $admin = $this->createUser('admin');
        $member = $this->createUser('member', 'General Member Attendance');
        $headCoach = $this->createUser('head_coach', 'General Employee Attendance');

        $this->createAttendance($member, [
            'attendance_date' => '2026-08-29',
            'check_in_time' => '2026-08-29 09:00:00',
            'check_out_time' => '2026-08-29 17:45:00',
        ]);
        $this->createAttendance($headCoach);

        $this->actingAs($admin)
            ->get(route('admin.absensi.index'))
            ->assertOk()
            ->assertSee('Semua Role')
            ->assertSee('Waktu Check-In')
            ->assertSee('Waktu Check-Out')
            ->assertSee('17:45')
            ->assertSee('General Member Attendance')
            ->assertSee('General Employee Attendance');
    }

    public function test_employee_attendance_page_formats_times_and_shows_dash_for_missing_values(): void
    {
        $admin = $this->createUser('admin');
        $checkoutOnlyEmployee = $this->createUser('head_coach', 'Checkout Only Employee');
        $checkInOnlyEmployee = $this->createUser('pt', 'Check-In Only Employee');

        $this->createAttendance($checkoutOnlyEmployee, [
            'attendance_status' => 'checkOut',
            'attendance_date' => '2026-08-29',
            'check_in_time' => null,
            'check_out_time' => '2026-08-29 18:30:00',
        ]);
        $this->createAttendance($checkInOnlyEmployee, [
            'attendance_date' => '2026-08-28',
            'check_in_time' => '2026-08-28 08:15:00',
            'check_out_time' => null,
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.absensi-karyawan.index'))
            ->assertOk()
            ->assertSee('Checkout Only Employee')
            ->assertSee('Check-In Only Employee')
            ->assertSee('29 Aug 2026')
            ->assertSee('18:30')
            ->assertSee('28 Aug 2026')
            ->assertSee('08:15');

        $contents = $response->getContent();

        $this->assertIsString($contents);
        $this->assertSame(
            2,
            substr_count($contents, '<span class="text-gray-500">-</span>'),
            'Each missing check-in or check-out value should render exactly one dash.',
        );
    }

    public function test_employee_attendance_date_filter_includes_checkout_only_and_legacy_rows(): void
    {
        $admin = $this->createUser('admin');
        $checkoutOnlyEmployee = $this->createUser('head_coach', 'Filtered Checkout Only');
        $legacyCheckInEmployee = $this->createUser('pt', 'Filtered Legacy Check-In');
        $legacyCheckoutEmployee = $this->createUser('kasir_gym', 'Filtered Legacy Checkout');
        $outsideRangeEmployee = $this->createUser('head_coach', 'Outside Date Range');

        $this->createAttendance($checkoutOnlyEmployee, [
            'attendance_status' => 'checkOut',
            'attendance_date' => '2026-08-29',
            'check_in_time' => null,
            'check_out_time' => '2026-08-29 18:00:00',
        ]);
        $this->createAttendance($legacyCheckInEmployee, [
            'attendance_date' => null,
            'check_in_time' => '2026-08-29 08:00:00',
            'check_out_time' => null,
        ]);
        $this->createAttendance($legacyCheckoutEmployee, [
            'attendance_status' => 'checkOut',
            'attendance_date' => null,
            'check_in_time' => null,
            'check_out_time' => '2026-08-29 19:00:00',
        ]);
        $this->createAttendance($outsideRangeEmployee, [
            'attendance_date' => '2026-08-28',
            'check_in_time' => '2026-08-28 08:00:00',
        ]);

        $this->actingAs($admin);

        Livewire::test('pages::dashboard.admin.absensi.index', ['employeesOnly' => true])
            ->call('setDateRange', '2026-08-29')
            ->assertSee('Filtered Checkout Only')
            ->assertSee('Filtered Legacy Check-In')
            ->assertSee('Filtered Legacy Checkout')
            ->assertDontSee('Outside Date Range');
    }

    public function test_employee_attendance_uses_stable_effective_order_and_paginates_ten_rows(): void
    {
        $admin = $this->createUser('admin');
        $newestEmployee = $this->createUser('head_coach', 'Newest Attendance');
        $tieOlderEmployee = $this->createUser('pt', 'Tie Older Attendance');
        $tieNewerEmployee = $this->createUser('kasir_gym', 'Tie Newer Attendance');

        $newestAttendance = $this->createAttendance($newestEmployee, [
            'attendance_date' => '2026-08-30',
            'check_in_time' => '2026-08-30 08:00:00',
        ]);
        $tieOlderAttendance = $this->createAttendance($tieOlderEmployee, [
            'attendance_date' => null,
            'check_in_time' => '2026-08-29 12:00:00',
        ]);
        $tieNewerAttendance = $this->createAttendance($tieNewerEmployee, [
            'attendance_status' => 'checkOut',
            'attendance_date' => '2026-08-29',
            'check_in_time' => null,
            'check_out_time' => '2026-08-29 12:00:00',
        ]);

        for ($day = 28; $day >= 20; $day--) {
            $employee = $this->createUser('head_coach', "Older Attendance {$day}");
            $date = "2026-08-{$day}";

            $this->createAttendance($employee, [
                'attendance_date' => $day % 2 === 0 ? $date : null,
                'check_in_time' => $date.' 08:00:00',
            ]);
        }

        $this->actingAs($admin);

        $component = Livewire::test('pages::dashboard.admin.absensi.index', ['employeesOnly' => true]);
        $attendances = $component->instance()->with()['attendances'];
        $attendanceIds = $attendances->getCollection()->pluck('id')->all();

        $this->assertSame(10, $attendances->perPage());
        $this->assertSame(12, $attendances->total());
        $this->assertCount(10, $attendanceIds);
        $this->assertSame([
            $newestAttendance->id,
            $tieNewerAttendance->id,
            $tieOlderAttendance->id,
        ], array_slice($attendanceIds, 0, 3));
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

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createAttendance(User $user, array $attributes = []): Attendance
    {
        return Attendance::create(array_merge([
            'user_id' => $user->id,
            'membership_id' => null,
            'type' => null,
            'attendance_status' => 'checkIn',
            'attendance_date' => null,
            'check_in_time' => now(),
            'check_out_time' => null,
        ], $attributes));
    }
}
