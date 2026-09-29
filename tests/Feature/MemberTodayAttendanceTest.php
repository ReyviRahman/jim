<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\MembershipAddon;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MemberTodayAttendanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_today_and_history_only_show_the_authenticated_members_check_ins(): void
    {
        $this->travelTo(now()->setDate(2026, 9, 13)->setTime(12, 0));
        $member = User::factory()->create(['role' => 'member', 'name' => 'BUDI sAnToSo']);
        $other = User::factory()->create(['role' => 'member']);
        $today = $this->attendance($member, '2026-09-13 08:15:00', '2026-09-13 10:25:00');
        $midnight = $this->attendance($member, '2026-09-13 00:00:00');
        $overnight = $this->attendance($member, '2026-09-12 23:00:00', '2026-09-13 01:30:00');
        $this->attendance($member, '2026-09-12 08:00:00', '2026-09-12 09:00:00');
        $this->attendance($member, '2026-09-14 00:00:00', '2026-09-14 01:00:00');
        $this->attendance($other, '2026-09-13 11:00:00', '2026-09-13 11:30:00');

        $component = Livewire::actingAs($member)->test('pages::dashboard.member.kehadiran')
            ->assertDontSee('Absensi hari ini')
            ->assertSee('Minggu, 13 September 2026')
            ->assertSee('08:15')
            ->assertSee('Check-in berhasil')
            ->assertSee('Halo, Budi Santoso! Siap jadi lebih kuat? Selamat latihan di Frans Gym!')
            ->assertDontSee('Riwayat Kehadiran')
            ->assertDontSee('Durasi Sesi')
            ->assertDontSee('10:25');
        $this->assertSame([$today->id, $midnight->id], $component->viewData('records')->values()->modelKeys());
        $this->assertSame(5, $component->viewData('history')->total());
        $component->assertSee('Riwayat Check-in')->assertSee('23:00')->assertDontSee('11:00');
        $this->actingAs($member)->get(route('member.kehadiran.index', ['tab' => 'check-out']))
            ->assertOk()->assertSee('Waktu check-in')->assertDontSee('Check-out')->assertDontSee('10:25');

    }

    public function test_empty_history_and_polling_have_clear_messages(): void
    {
        $member = User::factory()->create(['role' => 'member']);
        $component = Livewire::actingAs($member)->test('pages::dashboard.member.kehadiran')
            ->assertSee('Belum ada check-in')->assertSee('Belum ada riwayat check-in.')
            ->assertSee('Catatan kedatanganmu akan muncul di sini setelah kamu melakukan absensi.')
            ->assertDontSee('Buka absensi')->assertDontSee('Check-in berhasil');
        $this->attendance($member, now()->toDateTimeString());
        $component->call('$refresh')->assertSee('Check-in berhasil')->assertDontSee('Check-out');
        $this->assertCount(1, $component->viewData('records'));

    }

    public function test_history_is_paginated_stably_and_polling_preserves_page(): void
    {
        $member = User::factory()->create(['role' => 'member']);
        $ids = [];
        for ($i = 0; $i < 12; $i++) {
            $ids[] = $this->attendance($member, today()->subDay()->setTime(8, 0)->toDateTimeString())->id;
        }
        Attendance::create(['user_id' => $member->id, 'type' => 'gym', 'check_out_time' => now()]);
        $page = Livewire::actingAs($member)->test('pages::dashboard.member.kehadiran');
        $this->assertSame(array_slice(array_reverse($ids), 0, 10), $page->viewData('history')->modelKeys());
        $page->call('gotoPage', 2, 'historyPage')->call('$refresh')->assertSet('paginators.historyPage', 2);
        $this->assertSame([$ids[1], $ids[0]], $page->viewData('history')->modelKeys());
        $this->assertSame(12, $page->viewData('history')->total());
    }

    public function test_history_displays_gym_pt_addon_and_missing_package(): void
    {
        $addon = MembershipAddon::factory()->create(['name' => 'Bonus Gym Khusus']);
        $member = $addon->membership->user;
        $addon->membership->update(['gym_package_name_snapshot' => 'Gym snapshot', 'pt_package_name_snapshot' => 'PT snapshot']);
        foreach (['gym', 'pt'] as $type) {
            Attendance::create(['user_id' => $member->id, 'membership_id' => $addon->membership_id, 'type' => $type, 'check_in_time' => now()]);
        }
        Attendance::create(['user_id' => $member->id, 'membership_id' => $addon->membership_id, 'membership_addon_id' => $addon->id, 'type' => 'gym', 'check_in_time' => now()]);
        $this->attendance($member, now()->toDateTimeString());
        Livewire::actingAs($member)->test('pages::dashboard.member.kehadiran')
            ->assertSee('Gym snapshot')->assertSee('PT snapshot')->assertSee('Bonus Gym Khusus')->assertSee('Add-on Gratis')->assertSee('—');
    }

    public function test_guests_cannot_open_member_attendance(): void
    {
        $this->get(route('member.kehadiran.index'))->assertRedirect(route('login'));
    }

    private function attendance(User $member, string $checkIn, ?string $checkOut = null): Attendance
    {
        return Attendance::create([
            'user_id' => $member->id,
            'type' => 'gym',
            'check_in_time' => $checkIn,
            'check_out_time' => $checkOut,
        ]);
    }
}
