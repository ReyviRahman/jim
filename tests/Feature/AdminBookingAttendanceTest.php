<?php

namespace Tests\Feature;

use App\Models\Membership;
use App\Models\PtBooking;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminBookingAttendanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_mark_an_approved_booking_as_attended(): void
    {
        $admin = $this->createUser(['role' => 'admin']);
        $booking = $this->createBooking();

        $this->actingAs($admin);

        Livewire::test('pages::dashboard.admin.booking-jadwal.index')
            ->call('openDetailModal', $booking->id)
            ->assertSee('Tandai Hadir')
            ->call('markAsAttended', $booking->id)
            ->assertSee('Booking berhasil ditandai hadir.')
            ->assertDontSee('Tandai Hadir');

        $this->assertSame('attended', $booking->fresh()->attendance);
        $this->assertSame(9, $booking->membership->fresh()->remaining_sessions);
    }

    public function test_attendance_button_is_hidden_and_action_is_denied_for_non_admin(): void
    {
        $headCoach = User::factory()->headCoach()->create();
        $booking = $this->createBooking();

        $this->actingAs($headCoach);

        Livewire::test('pages::dashboard.admin.booking-jadwal.index')
            ->call('openDetailModal', $booking->id)
            ->assertDontSee('Tandai Hadir')
            ->call('markAsAttended', $booking->id)
            ->assertSee('Anda tidak memiliki izin untuk melakukan tindakan ini.');

        $this->assertSame('not_yet', $booking->fresh()->attendance);
        $this->assertSame(10, $booking->membership->fresh()->remaining_sessions);
    }

    public function test_head_coach_email_can_approve_booking_but_legacy_role_cannot(): void
    {
        $headCoach = User::factory()->headCoach()->create();
        $headCoachBooking = $this->createBooking(['status' => 'pending']);

        Livewire::actingAs($headCoach)
            ->test('pages::dashboard.admin.booking-jadwal.index')
            ->call('approveBooking', $headCoachBooking->id);

        $this->assertSame('approved', $headCoachBooking->fresh()->status);

        $legacyRoleUser = User::factory()->create(['role' => 'head_coach']);
        $legacyBooking = $this->createBooking(['status' => 'pending']);

        Livewire::actingAs($legacyRoleUser)
            ->test('pages::dashboard.admin.booking-jadwal.index')
            ->call('approveBooking', $legacyBooking->id)
            ->assertSee('Anda tidak memiliki izin untuk melakukan tindakan ini.');

        $this->assertSame('pending', $legacyBooking->fresh()->status);
    }

    public function test_inserted_booking_is_auto_approved_only_for_head_coach_email(): void
    {
        $headCoach = User::factory()->headCoach()->create();
        $headCoachTemplate = $this->createBooking();
        $headCoachMembership = $headCoachTemplate->membership;
        $headCoachTemplate->delete();

        Livewire::actingAs($headCoach)
            ->test('pages::dashboard.admin.booking-jadwal.index')
            ->set('insertMembershipId', $headCoachMembership->id)
            ->set('insertPtId', $headCoachMembership->pt_id)
            ->set('insertDate', today()->toDateString())
            ->set('insertTime', '11:00:00')
            ->call('saveInsertBooking');

        $this->assertSame(
            'approved',
            PtBooking::where('membership_id', $headCoachMembership->id)->firstOrFail()->status,
        );

        $legacyRoleUser = User::factory()->create(['role' => 'head_coach']);
        $legacyTemplate = $this->createBooking();
        $legacyMembership = $legacyTemplate->membership;
        $legacyTemplate->delete();

        Livewire::actingAs($legacyRoleUser)
            ->test('pages::dashboard.admin.booking-jadwal.index')
            ->set('insertMembershipId', $legacyMembership->id)
            ->set('insertPtId', $legacyMembership->pt_id)
            ->set('insertDate', today()->toDateString())
            ->set('insertTime', '12:00:00')
            ->call('saveInsertBooking');

        $this->assertSame(
            'pending',
            PtBooking::where('membership_id', $legacyMembership->id)->firstOrFail()->status,
        );
    }

    public function test_flexible_booking_persists_one_booking_with_its_selected_values(): void
    {
        $admin = $this->createUser(['role' => 'admin']);
        $membership = $this->createMembership(['remaining_sessions' => 5]);

        Livewire::actingAs($admin)
            ->test('pages::dashboard.admin.booking-jadwal.index')
            ->set('insertMembershipId', $membership->id)
            ->set('insertPtId', $membership->pt_id)
            ->set('insertType', 'fleksibel')
            ->set('insertDate', '2026-09-10')
            ->set('insertTime', '11:30:00')
            ->set('insertIsFree', true)
            ->call('saveInsertBooking')
            ->assertSee('Booking berhasil ditambahkan.');

        $booking = PtBooking::whereBelongsTo($membership)->sole();

        $this->assertSame('2026-09-10', $booking->booking_date->toDateString());
        $this->assertSame('11:30', $booking->booking_time->format('H:i'));
        $this->assertSame('fleksibel', $booking->type);
        $this->assertSame('pending', $booking->status);
        $this->assertTrue($booking->is_free);
    }

    public function test_keep_booking_requires_a_day_and_time_for_every_selected_day(): void
    {
        Carbon::setTestNow('2026-08-24 08:00:00');

        $admin = $this->createUser(['role' => 'admin']);
        $membership = $this->createMembership(['remaining_sessions' => 5]);
        $component = Livewire::actingAs($admin)
            ->test('pages::dashboard.admin.booking-jadwal.index')
            ->call('openInsertModal', 'senin', 9)
            ->set('insertMembershipId', $membership->id)
            ->set('insertPtId', $membership->pt_id)
            ->set('insertType', 'keep')
            ->call('saveInsertBooking')
            ->assertHasErrors('insertSelectedDays');

        $component
            ->set('insertSelectedDays', ['senin', 'selasa'])
            ->set('insertDayTimes', ['senin' => '09:00'])
            ->call('saveInsertBooking')
            ->assertHasErrors('insertDayTimes.selasa');

        $this->assertSame(0, PtBooking::whereBelongsTo($membership)->count());
    }

    public function test_keep_booking_creates_the_weekly_pattern_up_to_remaining_sessions(): void
    {
        Carbon::setTestNow('2026-08-24 08:00:00');

        $headCoach = User::factory()->headCoach()->create();
        $membership = $this->createMembership([
            'remaining_sessions' => 5,
            'pt_end_date' => '2026-10-31',
        ]);

        Livewire::actingAs($headCoach)
            ->test('pages::dashboard.admin.booking-jadwal.index')
            ->call('openInsertModal', 'senin', 9)
            ->set('insertMembershipId', $membership->id)
            ->set('insertPtId', $membership->pt_id)
            ->set('insertType', 'keep')
            ->set('insertSelectedDays', ['senin', 'selasa'])
            ->set('insertDayTimes', [
                'senin' => '09:00',
                'selasa' => '10:00',
            ])
            ->call('saveInsertBooking')
            ->assertSee('5 booking Keep berhasil ditambahkan.');

        $bookings = PtBooking::whereBelongsTo($membership)
            ->orderBy('booking_date')
            ->orderBy('booking_time')
            ->get();

        $this->assertSame([
            ['2026-08-24', '09:00'],
            ['2026-08-25', '10:00'],
            ['2026-08-31', '09:00'],
            ['2026-09-01', '10:00'],
            ['2026-09-07', '09:00'],
        ], $bookings->map(fn (PtBooking $booking): array => [
            $booking->booking_date->toDateString(),
            $booking->booking_time->format('H:i'),
        ])->all());
        $this->assertTrue($bookings->every(fn (PtBooking $booking): bool => $booking->membership_id === $membership->id));
        $this->assertTrue($bookings->every(fn (PtBooking $booking): bool => $booking->member_id === $membership->user_id));
        $this->assertTrue($bookings->every(fn (PtBooking $booking): bool => $booking->pt_id === $membership->pt_id));
        $this->assertTrue($bookings->every(fn (PtBooking $booking): bool => $booking->type === 'keep'));
        $this->assertTrue($bookings->every(fn (PtBooking $booking): bool => $booking->status === 'approved'));
        $this->assertTrue($bookings->every(fn (PtBooking $booking): bool => $booking->attendance === 'not_yet'));
        $this->assertTrue($bookings->every(fn (PtBooking $booking): bool => $booking->is_free === false));
    }

    public function test_keep_booking_uses_the_complete_displayed_week_even_when_dates_are_in_the_past(): void
    {
        Carbon::setTestNow('2026-08-26 12:00:00');

        $admin = $this->createUser(['role' => 'admin']);
        $membership = $this->createMembership([
            'remaining_sessions' => 10,
            'pt_end_date' => '2026-10-31',
        ]);

        Livewire::actingAs($admin)
            ->test('pages::dashboard.admin.booking-jadwal.index')
            ->call('openInsertModal', 'rabu', 13)
            ->set('insertMembershipId', $membership->id)
            ->set('insertPtId', $membership->pt_id)
            ->set('insertType', 'keep')
            ->set('insertSelectedDays', ['senin', 'selasa', 'rabu'])
            ->set('insertDayTimes', [
                'senin' => '09:00',
                'selasa' => '09:00',
                'rabu' => '09:00',
            ])
            ->call('saveInsertBooking')
            ->assertSee('10 booking Keep berhasil ditambahkan.');

        $this->assertSame([
            '2026-08-24',
            '2026-08-25',
            '2026-08-26',
            '2026-08-31',
            '2026-09-01',
            '2026-09-02',
            '2026-09-07',
            '2026-09-08',
            '2026-09-09',
            '2026-09-14',
        ], PtBooking::whereBelongsTo($membership)
            ->orderBy('booking_date')
            ->get()
            ->map(fn (PtBooking $booking): string => $booking->booking_date->toDateString())
            ->all());
    }

    public function test_keep_booking_uses_a_historical_week_and_stops_inclusively_at_pt_end_date(): void
    {
        Carbon::setTestNow('2026-09-09 12:00:00');

        $admin = $this->createUser(['role' => 'admin']);
        $membership = $this->createMembership([
            'remaining_sessions' => 10,
            'pt_end_date' => '2026-08-25',
        ]);

        Livewire::actingAs($admin)
            ->test('pages::dashboard.admin.booking-jadwal.index')
            ->set('dateFrom', '2026-08-24')
            ->set('dateTo', '2026-08-30')
            ->call('openInsertModal', 'senin', 9)
            ->set('insertMembershipId', $membership->id)
            ->set('insertPtId', $membership->pt_id)
            ->set('insertType', 'keep')
            ->set('insertSelectedDays', ['senin', 'selasa', 'rabu'])
            ->set('insertDayTimes', [
                'senin' => '09:00',
                'selasa' => '09:00',
                'rabu' => '09:00',
            ])
            ->call('saveInsertBooking')
            ->assertSee('2 booking Keep berhasil ditambahkan sampai masa PT berakhir.');

        $this->assertSame([
            '2026-08-24',
            '2026-08-25',
        ], PtBooking::whereBelongsTo($membership)
            ->orderBy('booking_date')
            ->get()
            ->map(fn (PtBooking $booking): string => $booking->booking_date->toDateString())
            ->all());
    }

    public function test_keep_booking_creates_nothing_when_every_occurrence_is_after_pt_end_date(): void
    {
        Carbon::setTestNow('2026-08-24 08:00:00');

        $admin = $this->createUser(['role' => 'admin']);
        $membership = $this->createMembership([
            'remaining_sessions' => 3,
            'pt_end_date' => '2026-08-23',
        ]);

        Livewire::actingAs($admin)
            ->test('pages::dashboard.admin.booking-jadwal.index')
            ->call('openInsertModal', 'senin', 9)
            ->set('insertMembershipId', $membership->id)
            ->set('insertPtId', $membership->pt_id)
            ->set('insertType', 'keep')
            ->set('insertSelectedDays', ['senin'])
            ->set('insertDayTimes', ['senin' => '09:00'])
            ->call('saveInsertBooking')
            ->assertHasErrors('insertBooking')
            ->assertSet('showInsertModal', true);

        $this->assertSame(0, PtBooking::whereBelongsTo($membership)->count());
    }

    public function test_keep_booking_reserves_only_active_non_free_sessions_and_skips_duplicates(): void
    {
        Carbon::setTestNow('2026-08-24 08:00:00');

        $admin = $this->createUser(['role' => 'admin']);
        $membership = $this->createMembership([
            'remaining_sessions' => 5,
            'pt_end_date' => '2026-10-31',
        ]);

        $this->createBookingForMembership($membership, [
            'booking_date' => '2026-08-24',
            'booking_time' => '09:00:00',
            'status' => 'pending',
        ]);
        $this->createBookingForMembership($membership, [
            'booking_date' => '2026-08-28',
            'booking_time' => '15:00:00',
            'status' => 'approved',
        ]);

        foreach ([
            ['status' => 'cancelled', 'attendance' => 'not_yet', 'is_free' => false],
            ['status' => 'rejected', 'attendance' => 'not_yet', 'is_free' => false],
            ['status' => 'approved', 'attendance' => 'attended', 'is_free' => false],
            ['status' => 'approved', 'attendance' => 'noshow', 'is_free' => false],
            ['status' => 'approved', 'attendance' => 'not_yet', 'is_free' => true],
        ] as $index => $attributes) {
            $this->createBookingForMembership($membership, [
                'booking_date' => Carbon::parse('2026-09-10')->addDays($index)->toDateString(),
                'booking_time' => '15:00:00',
                ...$attributes,
            ]);
        }

        Livewire::actingAs($admin)
            ->test('pages::dashboard.admin.booking-jadwal.index')
            ->call('openInsertModal', 'senin', 9)
            ->set('insertMembershipId', $membership->id)
            ->set('insertPtId', $membership->pt_id)
            ->set('insertType', 'keep')
            ->set('insertSelectedDays', ['senin', 'selasa'])
            ->set('insertDayTimes', [
                'senin' => '09:00',
                'selasa' => '10:00',
            ])
            ->call('saveInsertBooking')
            ->assertSee('3 booking Keep berhasil ditambahkan.');

        $keepBookings = PtBooking::whereBelongsTo($membership)
            ->where('type', 'keep')
            ->orderBy('booking_date')
            ->get();

        $this->assertSame([
            '2026-08-25',
            '2026-08-31',
            '2026-09-01',
        ], $keepBookings->map(fn (PtBooking $booking): string => $booking->booking_date->toDateString())->all());
        $this->assertSame(5, PtBooking::whereBelongsTo($membership)
            ->whereIn('status', ['pending', 'approved'])
            ->where('attendance', 'not_yet')
            ->where('is_free', false)
            ->count());
    }

    public function test_keep_booking_rejects_free_sessions_and_repeated_submit_cannot_overbook(): void
    {
        Carbon::setTestNow('2026-08-24 08:00:00');

        $admin = $this->createUser(['role' => 'admin']);
        $membership = $this->createMembership([
            'remaining_sessions' => 3,
            'pt_end_date' => '2026-10-31',
        ]);
        $component = Livewire::actingAs($admin)
            ->test('pages::dashboard.admin.booking-jadwal.index')
            ->call('openInsertModal', 'senin', 9)
            ->set('insertMembershipId', $membership->id)
            ->set('insertPtId', $membership->pt_id)
            ->set('insertType', 'keep')
            ->set('insertSelectedDays', ['senin'])
            ->set('insertDayTimes', ['senin' => '09:00'])
            ->set('insertIsFree', true)
            ->call('saveInsertBooking')
            ->assertHasErrors('insertIsFree');

        $component
            ->set('insertIsFree', false)
            ->call('saveInsertBooking')
            ->assertSee('3 booking Keep berhasil ditambahkan.')
            ->assertSet('showInsertModal', false)
            ->assertSet('insertSelectedDays', [])
            ->assertSet('insertDayTimes', []);

        $component
            ->call('openInsertModal', 'senin', 9)
            ->set('insertMembershipId', $membership->id)
            ->set('insertPtId', $membership->pt_id)
            ->set('insertType', 'keep')
            ->set('insertSelectedDays', ['senin'])
            ->set('insertDayTimes', ['senin' => '09:00'])
            ->call('saveInsertBooking')
            ->assertHasNoErrors('insertBooking')
            ->assertSet('showInsertModal', true)
            ->assertSet('showInsertErrorModal', true)
            ->assertSet('insertErrorMessage', 'Semua sisa sesi membership sudah memiliki booking aktif.')
            ->assertSet('insertMembershipId', $membership->id)
            ->assertSet('insertType', 'keep')
            ->assertSet('insertSelectedDays', ['senin'])
            ->assertSet('insertDayTimes', ['senin' => '09:00'])
            ->assertSee('Booking Tidak Dapat Dibuat')
            ->assertSee('Mengerti');

        $component
            ->call('closeInsertErrorModal')
            ->assertSet('showInsertErrorModal', false)
            ->assertSet('insertErrorMessage', '')
            ->assertSet('showInsertModal', true)
            ->assertSet('insertMembershipId', $membership->id)
            ->assertSet('insertSelectedDays', ['senin'])
            ->assertSet('insertDayTimes', ['senin' => '09:00']);

        $component
            ->set('insertType', 'fleksibel')
            ->set('insertDate', '2026-08-25')
            ->set('insertTime', '14:00:00')
            ->call('saveInsertBooking')
            ->assertHasNoErrors('insertBooking')
            ->assertSet('showInsertModal', true)
            ->assertSet('showInsertErrorModal', true)
            ->assertSet('insertErrorMessage', 'Semua sisa sesi membership sudah memiliki booking aktif.')
            ->assertSet('insertMembershipId', $membership->id)
            ->assertSet('insertType', 'fleksibel')
            ->assertSet('insertDate', '2026-08-25')
            ->assertSet('insertTime', '14:00:00')
            ->assertSee('Booking Tidak Dapat Dibuat');

        $component
            ->call('closeInsertErrorModal')
            ->call('closeInsertModal')
            ->assertSet('showInsertErrorModal', false)
            ->assertSet('insertErrorMessage', '')
            ->assertSet('showInsertModal', false)
            ->assertSet('insertMembershipId', null)
            ->assertSet('insertType', 'fleksibel')
            ->assertSet('insertSelectedDays', [])
            ->assertSet('insertDayTimes', []);

        $this->assertSame(3, PtBooking::whereBelongsTo($membership)->count());
    }

    public function test_admin_cannot_mark_a_non_approved_booking_as_attended(): void
    {
        $admin = $this->createUser(['role' => 'admin']);
        $booking = $this->createBooking(['status' => 'pending']);

        $this->actingAs($admin);

        Livewire::test('pages::dashboard.admin.booking-jadwal.index')
            ->call('markAsAttended', $booking->id)
            ->assertSee('Booking tidak valid untuk ditandai hadir.');

        $this->assertSame('not_yet', $booking->fresh()->attendance);
        $this->assertSame(10, $booking->membership->fresh()->remaining_sessions);
    }

    public function test_booking_cards_normalize_supported_whatsapp_number_formats(): void
    {
        $admin = $this->createUser(['role' => 'admin']);
        $numberFormats = [
            ['stored' => '081234567890', 'expected' => '6281234567890'],
            ['stored' => '81234567891', 'expected' => '6281234567891'],
            ['stored' => '6281234567892', 'expected' => '6281234567892'],
            ['stored' => '+62 812-3456-7893', 'expected' => '6281234567893'],
        ];

        foreach ($numberFormats as $numberFormat) {
            $booking = $this->createBooking();
            $booking->member->update(['phone' => $numberFormat['stored']]);

            $this->assertSame($numberFormat['stored'], $booking->member->fresh()->phone);
        }

        $component = Livewire::actingAs($admin)
            ->test('pages::dashboard.admin.booking-jadwal.index');

        foreach ($numberFormats as $numberFormat) {
            $component->assertSeeHtml('href="https://wa.me/'.$numberFormat['expected'].'?text=');
        }
    }

    public function test_booking_card_renders_a_prefilled_whatsapp_link_for_each_member(): void
    {
        $admin = $this->createUser(['role' => 'admin']);
        $booking = $this->createBooking();
        $booking->member->update([
            'name' => 'Member Utama',
            'phone' => '081234567890',
        ]);

        $additionalMember = $this->createUser([
            'name' => 'Member Tambahan',
            'phone' => '082345678901',
        ]);

        $booking->membership->members()->attach([
            $booking->member_id,
            $additionalMember->id,
        ]);

        $booking->load(['member', 'pt']);
        $encodedDate = rawurlencode('Tanggal: '.$booking->booking_date->locale('id')->isoFormat('dddd, D MMMM YYYY'));
        $encodedTime = rawurlencode('Waktu: '.$booking->booking_time->format('H:i'));
        $encodedCoach = rawurlencode('Coach: '.$booking->pt->name);
        $encodedSession = rawurlencode('Sesi ke-1');

        $component = Livewire::actingAs($admin)
            ->test('pages::dashboard.admin.booking-jadwal.index')
            ->assertSeeHtml('href="https://wa.me/6281234567890?text=Halo%20Member%20Utama%2C%0A%0A')
            ->assertSeeHtml('href="https://wa.me/6282345678901?text=Halo%20Member%20Tambahan%2C%0A%0A')
            ->assertSeeHtml($encodedDate)
            ->assertSeeHtml($encodedTime)
            ->assertSeeHtml($encodedCoach)
            ->assertSeeHtml($encodedSession)
            ->assertSeeHtml('target="_blank"')
            ->assertSeeHtml('rel="noopener noreferrer"')
            ->assertSeeHtml('x-on:click.stop')
            ->assertSeeHtml('aria-label="Kirim WhatsApp ke Member Utama"')
            ->assertSeeHtml('aria-label="Kirim WhatsApp ke Member Tambahan"');

        $this->assertSame(2, substr_count($component->html(), $encodedSession));
    }

    public function test_booking_cards_and_whatsapp_messages_show_numbered_and_free_sessions(): void
    {
        $admin = $this->createUser(['role' => 'admin']);
        $firstBooking = $this->createBooking([
            'booking_time' => '09:00:00',
        ]);
        $firstBooking->member->update(['phone' => '081234567890']);

        $freeBooking = $firstBooking->replicate();
        $freeBooking->fill([
            'booking_time' => '10:00:00',
            'is_free' => true,
        ]);
        $freeBooking->save();

        $secondBooking = $firstBooking->replicate();
        $secondBooking->fill([
            'booking_time' => '11:00:00',
            'is_free' => false,
        ]);
        $secondBooking->save();

        Livewire::actingAs($admin)
            ->test('pages::dashboard.admin.booking-jadwal.index')
            ->assertSee('Sesi ke-1')
            ->assertSee('Free')
            ->assertSee('Sesi ke-2')
            ->assertDontSee('Sesi ke-3')
            ->assertSeeHtml(rawurlencode('Sesi ke-1'))
            ->assertSeeHtml(rawurlencode('Sesi Free'))
            ->assertSeeHtml(rawurlencode('Sesi ke-2'));
    }

    public function test_booking_card_omits_whatsapp_link_for_an_invalid_number(): void
    {
        $admin = $this->createUser(['role' => 'admin']);
        $booking = $this->createBooking();
        $booking->member->update(['phone' => '074123456789']);

        Livewire::actingAs($admin)
            ->test('pages::dashboard.admin.booking-jadwal.index')
            ->assertDontSeeHtml('https://wa.me/');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createBooking(array $attributes = []): PtBooking
    {
        return $this->createBookingForMembership($this->createMembership(), $attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createMembership(array $attributes = []): Membership
    {
        $member = $this->createUser();
        $personalTrainer = $this->createUser(['role' => 'pt']);

        return Membership::create([
            'user_id' => $member->id,
            'pt_id' => $personalTrainer->id,
            'type' => 'pt',
            'base_price' => 100000,
            'price_paid' => 100000,
            'total_paid' => 100000,
            'payment_status' => 'paid',
            'start_date' => today(),
            'pt_end_date' => today()->addMonth(),
            'total_sessions' => 10,
            'remaining_sessions' => 10,
            'status' => 'active',
            ...$attributes,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createBookingForMembership(Membership $membership, array $attributes = []): PtBooking
    {
        return PtBooking::create([
            'membership_id' => $membership->id,
            'member_id' => $membership->user_id,
            'pt_id' => $membership->pt_id,
            'booking_date' => today(),
            'booking_time' => '10:00:00',
            'status' => 'approved',
            'attendance' => 'not_yet',
            'is_free' => false,
            ...$attributes,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createUser(array $attributes = []): User
    {
        return User::factory()->create([
            'age' => 30,
            'gender' => 'Laki-laki',
            'phone' => fake()->unique()->numerify('08##########'),
            ...$attributes,
        ]);
    }
}
