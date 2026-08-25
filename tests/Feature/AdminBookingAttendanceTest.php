<?php

namespace Tests\Feature;

use App\Models\Membership;
use App\Models\PtBooking;
use App\Models\User;
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
        $member = $this->createUser();
        $personalTrainer = $this->createUser(['role' => 'pt']);
        $membership = Membership::create([
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
        ]);

        return PtBooking::create([
            'membership_id' => $membership->id,
            'member_id' => $member->id,
            'pt_id' => $personalTrainer->id,
            'booking_date' => today(),
            'booking_time' => '10:00:00',
            'status' => 'approved',
            'attendance' => 'not_yet',
            'is_free' => false,
            ...$attributes,
        ]);
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
