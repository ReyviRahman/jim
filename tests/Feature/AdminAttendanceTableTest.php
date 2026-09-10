<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AttendanceEmployee;
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
        $member = $this->createUser('member');

        $this->createAttendance($member);

        $this->actingAs($admin);

        Livewire::test('pages::dashboard.admin.absensi.index')
            ->assertSee('Nama User')
            ->assertSee('Role User')
            ->assertSee('Member')
            ->assertDontSee('Tipe Kedatangan')
            ->assertDontSee('Detail Paket');
    }

    public function test_employee_attendance_page_only_shows_non_member_attendances(): void
    {
        $admin = $this->createUser('admin');
        $member = $this->createUser('member', 'Member Attendance');
        $headCoach = $this->createUser('kasir_minum', 'Employee Attendance');

        $this->createAttendance($member, [
            'attendance_date' => '2026-08-29',
            'check_in_time' => '2026-08-29 09:00:00',
            'check_out_time' => '2026-08-29 18:00:00',
        ]);
        AttendanceEmployee::factory()->create([
            'user_id' => $headCoach->id,
            'nama_di_alat' => 'Karyawan di Hikvision',
            'attendance_date' => '2026-08-29',
            'check_in_time' => '2026-08-29 08:05:00',
            'check_out_time' => '2026-08-29 17:45:00',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.absensi-karyawan.index', ['month' => '2026-08']))
            ->assertOk()
            ->assertSee('Absensi karyawan')
            ->assertSee('Employee Attendance')
            ->assertDontSee('Member Attendance')
            ->assertSee('Hasil QR muncul disini')
            ->assertSee('Agustus 2026')
            ->assertSee('Nama karyawan')
            ->assertDontSee('Pilih Rentang Tanggal');

        Livewire::test('pages::dashboard.admin.absensi.index', ['employeesOnly' => true])
            ->call('openAttendanceCell', $headCoach->id, '2026-08-29')
            ->assertSee('Karyawan di Hikvision')->assertSee('08:05')->assertSee('17:45');
    }

    public function test_general_attendance_page_only_shows_members_without_role_filter(): void
    {
        $admin = $this->createUser('admin');
        $member = $this->createUser('member', 'General Member Attendance');
        $headCoach = $this->createUser('kasir_minum', 'General Employee Attendance');

        $this->createAttendance($member, [
            'nama_di_alat' => 'Member di Hikvision',
            'attendance_date' => '2026-08-29',
            'check_in_time' => '2026-08-29 09:00:00',
            'check_out_time' => '2026-08-29 17:45:00',
        ]);
        $this->createAttendance($headCoach);

        $this->actingAs($admin)
            ->get(route('admin.absensi.index'))
            ->assertOk()
            ->assertSee('Data Absensi Member &amp; Scanner', false)
            ->assertDontSee('Semua Role')
            ->assertSee('Nama di Alat')
            ->assertSee('Member di Hikvision')
            ->assertSee('Waktu Check-In')
            ->assertDontSee('Waktu Check-Out')
            ->assertDontSee('17:45')
            ->assertSee('General Member Attendance')
            ->assertDontSee('General Employee Attendance');
    }

    public function test_employee_attendance_page_formats_times_and_shows_dash_for_missing_values(): void
    {
        $admin = $this->createUser('admin');
        $checkoutOnlyEmployee = $this->createUser('kasir_minum', 'Checkout Only Employee');
        $checkInOnlyEmployee = $this->createUser('pt', 'Check-In Only Employee');

        $this->createEmployeeAttendance($checkoutOnlyEmployee, [
            'attendance_date' => '2026-08-29',
            'check_in_time' => '2026-08-29 08:00:00',
            'check_out_time' => '2026-08-29 18:30:00',
        ]);
        $this->createEmployeeAttendance($checkInOnlyEmployee, [
            'attendance_date' => '2026-08-28',
            'check_in_time' => '2026-08-28 08:15:00',
            'check_out_time' => null,
        ]);

        $this->actingAs($admin);

        $response = Livewire::test('pages::dashboard.admin.absensi.index', ['employeesOnly' => true])->set('month', '2026-08')
            ->assertSee('Checkout Only Employee')
            ->assertSee('Check-In Only Employee')
            ->call('openAttendanceCell', $checkoutOnlyEmployee->id, '2026-08-29')->assertSee('29 Agustus 2026')
            ->assertSee('18:30')
            ->call('openAttendanceCell', $checkInOnlyEmployee->id, '2026-08-28')->assertSee('28 Agustus 2026')
            ->assertSee('08:15');

        $contents = $response->html();

        $this->assertIsString($contents);
        $this->assertStringContainsString('Belum ada data', $contents);
        $this->assertStringContainsString('Keluar', $contents);
    }

    public function test_employee_attendance_date_filter_excludes_checkout_only_and_legacy_rows(): void
    {
        $admin = $this->createUser('admin');
        $checkoutOnlyEmployee = $this->createUser('kasir_minum', 'Filtered Checkout Only');
        $legacyCheckInEmployee = $this->createUser('pt', 'Filtered Legacy Check-In');
        $legacyCheckoutEmployee = $this->createUser('kasir_gym', 'Filtered Legacy Checkout');
        $outsideRangeEmployee = $this->createUser('kasir_minum', 'Outside Date Range');

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
            ->set('month', '2026-08')
            ->assertSee('Filtered Checkout Only')
            ->assertSee('Filtered Legacy Check-In')
            ->assertSee('Filtered Legacy Checkout')
            ->assertSee('Belum ada data')
            ->assertDontSee('Detail Filtered');
    }

    public function test_employee_attendance_groups_all_employees_without_paginating_scan_rows(): void
    {
        $admin = $this->createUser('admin');
        $newestEmployee = $this->createUser('kasir_minum', 'Newest Attendance');
        $tieOlderEmployee = $this->createUser('pt', 'Tie Older Attendance');
        $tieNewerEmployee = $this->createUser('kasir_gym', 'Tie Newer Attendance');

        $newestAttendance = $this->createEmployeeAttendance($newestEmployee, [
            'attendance_date' => '2026-08-30',
            'check_in_time' => '2026-08-30 08:00:00',
        ]);
        $tieOlderAttendance = $this->createEmployeeAttendance($tieOlderEmployee, [
            'attendance_date' => '2026-08-29',
            'check_in_time' => '2026-08-29 12:00:00',
        ]);
        $tieNewerAttendance = $this->createEmployeeAttendance($tieNewerEmployee, [
            'attendance_date' => '2026-08-29',
            'check_in_time' => '2026-08-29 12:00:00',
            'check_out_time' => '2026-08-29 12:00:00',
        ]);

        for ($day = 28; $day >= 20; $day--) {
            $employee = $this->createUser('kasir_minum', "Older Attendance {$day}");
            $date = "2026-08-{$day}";

            $this->createEmployeeAttendance($employee, [
                'attendance_date' => $date,
                'check_in_time' => $date.' 08:00:00',
            ]);
        }

        $this->actingAs($admin);

        $component = Livewire::test('pages::dashboard.admin.absensi.index', ['employeesOnly' => true])->set('month', '2026-08');
        $data = $component->instance()->with();
        $this->assertSame(12, $data['employeeCount']);
        $this->assertCount(31, $data['days']);
        $this->assertSame($newestAttendance->id, $data['cells'][$newestEmployee->id]['2026-08-30']->first()->id);
        $this->assertSame($tieOlderAttendance->id, $data['cells'][$tieOlderEmployee->id]['2026-08-29']->first()->id);
        $this->assertSame($tieNewerAttendance->id, $data['cells'][$tieNewerEmployee->id]['2026-08-29']->first()->id);
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

    public function test_name_search_preserves_role_and_date_filters_on_both_attendance_pages(): void
    {
        $this->actingAs($this->createUser('admin'));

        foreach (['member', 'kasir_minum'] as $role) {
            foreach (['Budi Santoso', 'Siti Aminah'] as $name) {
                $user = $this->createUser($role, $name);
                foreach (['2026-08-29', '2026-08-28'] as $date) {
                    if ($role === 'member') {
                        $this->createAttendance($user, ['attendance_date' => $date]);
                    } else {
                        $this->createEmployeeAttendance($user, ['attendance_date' => $date]);
                    }
                }
            }
        }

        foreach ([false, true] as $employeesOnly) {
            $component = Livewire::test('pages::dashboard.admin.absensi.index', compact('employeesOnly'))
                ->assertSee($employeesOnly ? 'Cari nama karyawan...' : 'Cari nama user...')
                ->call('setDateRange', '2026-08-29')
                ->set('search', '  Santoso  ')
                ->assertSee('Budi Santoso')
                ->assertDontSee('Siti Aminah');

            if ($employeesOnly) {
                $component->set('month', '2026-08');
                $this->assertSame(1, $component->instance()->with()['employeeCount']);
                $component->set('search', 'TidakDitemukan')->assertSee('Tidak ada karyawan yang cocok');
                $component->set('search', 'Santoso')->call('nextMonth')->assertSee('Budi Santoso')->assertDontSee('Siti Aminah');

                continue;
            }
            $attendances = $component->instance()->with()['attendances'];
            $this->assertSame(1, $attendances->total());
            $this->assertSame($employeesOnly ? 'kasir_minum' : 'member', $attendances->first()->user->role);

            $component->set('search', 'TidakDitemukan')->assertSee('Tidak ada data absensi.');
            $component->set('search', '   ')->assertSee('Budi Santoso')->assertSee('Siti Aminah');
            $this->assertSame(2, $component->instance()->with()['attendances']->total());
        }
    }

    public function test_name_search_resets_pagination_on_both_attendance_pages(): void
    {
        $this->actingAs($this->createUser('admin'));

        foreach (['member', 'kasir_minum'] as $role) {
            $user = $this->createUser($role, 'Budi Santoso');

            for ($index = 0; $index < 11; $index++) {
                if ($role === 'member') {
                    $this->createAttendance($user);
                } else {
                    $this->createEmployeeAttendance($user, ['attendance_date' => now()->subDays($index)->toDateString()]);
                }
            }
        }

        foreach ([false, true] as $employeesOnly) {
            Livewire::test('pages::dashboard.admin.absensi.index', compact('employeesOnly'))
                ->call('gotoPage', 2)
                ->assertSet('paginators.page', 2)
                ->set('search', 'Santoso')
                ->assertSet('paginators.page', 1)
                ->assertSee('Budi Santoso');
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createEmployeeAttendance(User $user, array $attributes = []): AttendanceEmployee
    {
        return AttendanceEmployee::factory()->create(array_merge(['user_id' => $user->id], $attributes));
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
