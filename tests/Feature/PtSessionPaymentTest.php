<?php

namespace Tests\Feature;

use App\Models\Membership;
use App\Models\PtBooking;
use App\Models\PtPaymentBatch;
use App\Models\PtPaymentBatchItem;
use App\Models\PtSessionCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PtSessionPaymentTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_payment_preview_is_sorted_by_member_name(): void
    {
        $pt = $this->createUser(['role' => 'pt']);
        $admin = $this->createUser(['role' => 'admin']);

        foreach (['Zahra', 'Andi', 'Budi'] as $name) {
            $member = $this->createUser(['name' => $name]);
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

            PtBooking::create([
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
        }

        $this->actingAs($admin);

        Livewire::test('pages::dashboard.admin.sesi-pt.detail', ['user' => $pt])
            ->call('openPaymentPreview')
            ->assertSet('paymentPreviewRows.0.member_name', 'Andi')
            ->assertSet('paymentPreviewRows.1.member_name', 'Budi')
            ->assertSet('paymentPreviewRows.2.member_name', 'Zahra')
            ->set('paymentPreviewSearch', 'bud')
            ->assertCount('filteredPaymentPreviewRows', 1)
            ->assertSet('filteredPaymentPreviewRows.0.member_name', 'Budi')
            ->set('paymentPreviewSearch', '')
            ->assertCount('filteredPaymentPreviewRows', 3)
            ->assertSet('filteredPaymentPreviewRows.0.member_name', 'Andi');
    }

    public function test_substitute_pt_membership_uses_sls_category(): void
    {
        $primaryPt = $this->createUser(['role' => 'pt']);
        $substitutePt = $this->createUser(['role' => 'pt']);
        $member = $this->createUser(['name' => 'Member Cadangan']);
        $admin = $this->createUser(['role' => 'admin']);

        $membership = Membership::create([
            'user_id' => $member->id,
            'type' => 'pt',
            'pt_id' => $primaryPt->id,
            'base_price' => 100000,
            'price_paid' => 100000,
            'total_paid' => 100000,
            'payment_status' => 'paid',
            'start_date' => today(),
            'pt_end_date' => today()->addMonth(),
            'status' => 'active',
        ]);

        PtBooking::create([
            'membership_id' => $membership->id,
            'member_id' => $member->id,
            'pt_id' => $substitutePt->id,
            'booking_date' => today(),
            'booking_time' => '09:00:00',
            'status' => 'approved',
            'attendance' => 'attended',
            'is_free' => false,
            'is_paid' => false,
        ]);

        PtSessionCategory::create([
            'pt_id' => $substitutePt->id,
            'category' => 'SLS',
            'amount' => 50000,
        ]);

        $this->assertSame('SDR', $membership->getPtCategoryLabelFor($primaryPt->id));
        $this->assertSame('SLS', $membership->getPtCategoryLabelFor($substitutePt->id));

        $this->actingAs($admin);

        Livewire::test('pages::dashboard.admin.sesi-pt.detail', ['user' => $substitutePt])
            ->assertSeeInOrder(['Membership (PT Pengganti/Cadangan)', $member->name, 'SLS'])
            ->call('openPaymentPreview')
            ->assertSet('paymentPreviewRows.0.category', 'SLS')
            ->assertSet('paymentPreviewRows.0.nominal', 50000);
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
