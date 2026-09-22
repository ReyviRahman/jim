<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MemberTodayAttendanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_today_tabs_only_show_the_authenticated_members_events(): void
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
        $component->set('tab', 'check-out')->assertSee('10:25')->assertSee('01:30')->assertDontSee('08:15')->assertDontSee('Check-in berhasil')
            ->assertSee('Check-out berhasil')
            ->assertSee('Durasi Sesi')
            ->assertSee('2 jam 10 menit')
            ->assertSee('2 jam 30 menit')
            ->assertSee('Sesi latihanmu tercatat. Sampai jumpa lagi, Budi Santoso!');
        $this->assertSame([$today->id, $overnight->id], $component->viewData('records')->values()->modelKeys());
        $component->set('tab', 'invalid')->assertSee('08:15')->assertDontSee('10:25');
        $this->actingAs($member)->get(route('member.kehadiran.index', ['tab' => 'check-out']))
            ->assertOk()->assertSee('Waktu check-out')->assertSee('Minggu, 13 September 2026')->assertSee('10:25');
    }

    public function test_empty_tabs_and_pending_checkout_have_clear_messages(): void
    {
        $member = User::factory()->create(['role' => 'member']);
        $component = Livewire::actingAs($member)->test('pages::dashboard.member.kehadiran')
            ->assertSee('Belum ada check-in')
            ->assertSee('Catatan kedatanganmu akan muncul di sini setelah kamu melakukan absensi.')
            ->assertDontSee('Buka absensi')->assertDontSee('Check-in berhasil');
        $this->attendance($member, now()->toDateTimeString());
        $component->set('tab', 'check-out')->assertSee('Belum ada check-out')->assertDontSee('Buka absensi');
        $this->assertCount(0, $component->viewData('records'));
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
