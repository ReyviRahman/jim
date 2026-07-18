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
        $headCoach = $this->createUser(['role' => 'head_coach']);
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
