<?php

namespace Tests\Feature;

use App\Models\Membership;
use App\Models\PtBooking;
use App\Models\PtPaymentBatch;
use App\Models\PtPaymentBatchItem;
use App\Models\User;
use Livewire\Livewire;
use Tests\TestCase;

class PtSessionPaymentTest extends TestCase
{
    public function test_paid_sessions_can_be_added_to_a_new_payment_batch_for_the_same_period(): void
    {
        $pt = $this->createUser(['role' => 'pt']);
        $member = $this->createUser();
        $admin = $this->createUser(['role' => 'admin']);
        $membership = Membership::create([
            'user_id' => $member->id,
            'type' => 'pt',
            'pt_id' => $pt->id,
            'base_price' => 100000,
            'price_paid' => 100000,
            'total_paid' => 100000,
            'payment_status' => 'paid',
            'start_date' => today(),
            'pt_end_date' => today()->addMonth(),
            'status' => 'active',
        ]);
        $booking = PtBooking::create([
            'membership_id' => $membership->id,
            'member_id' => $member->id,
            'pt_id' => $pt->id,
            'booking_date' => today(),
            'booking_time' => '09:00:00',
            'status' => 'approved',
            'attendance' => 'attended',
            'is_free' => false,
            'is_paid' => false,
        ]);

        $this->actingAs($admin);

        Livewire::test('pages::dashboard.admin.sesi-pt.detail', ['user' => $pt])
            ->call('paySessions')
            ->call('paySessions');

        $this->assertSame(2, PtPaymentBatch::query()->count());
        $this->assertSame(2, PtPaymentBatchItem::query()->where('pt_booking_id', $booking->id)->count());
        $this->assertTrue($booking->fresh()->is_paid);
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
