<?php

namespace Tests\Feature;

use App\Models\Membership;
use App\Models\PtBooking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MemberAttendanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_absensi_selector_rejects_a_membership_the_member_cannot_access(): void
    {
        $member = $this->createUser();
        $otherMember = $this->createUser();
        $ownedMembership = $this->createMembership($member);
        $unrelatedMembership = $this->createMembership($otherMember);

        Livewire::actingAs($member)
            ->test('pages::dashboard.member.absensi')
            ->assertSet('selectedMembershipId', $ownedMembership->id)
            ->set('selectedMembershipId', $unrelatedMembership->id)
            ->assertForbidden();
    }

    public function test_shared_pt_membership_only_returns_bookings_owned_by_the_authenticated_member(): void
    {
        $owner = $this->createUser();
        $sharedMember = $this->createUser();
        $personalTrainer = $this->createUser(['role' => 'pt']);
        $membership = $this->createMembership($owner, [
            'type' => 'pt',
            'pt_id' => $personalTrainer->id,
            'membership_end_date' => null,
            'pt_end_date' => today()->addMonth()->toDateString(),
            'total_sessions' => 10,
            'remaining_sessions' => 10,
        ]);
        $membership->members()->attach($sharedMember);

        $sharedMemberBooking = $this->createBooking($membership, $sharedMember, [
            'booking_date' => today()->addDay()->toDateString(),
            'booking_time' => '09:00:00',
        ]);
        $ownerBooking = $this->createBooking($membership, $owner, [
            'booking_date' => today()->addDays(2)->toDateString(),
            'booking_time' => '10:00:00',
        ]);

        $component = Livewire::actingAs($sharedMember)
            ->test('pages::dashboard.member.absensi')
            ->assertSet('selectedMembershipId', $membership->id);

        $eligibleBookingIds = $component->viewData('eligibleBookings')->modelKeys();

        $this->assertSame([$sharedMemberBooking->id], $eligibleBookingIds);
        $this->assertNotContains($ownerBooking->id, $eligibleBookingIds);
    }

    public function test_inactive_membership_is_not_eligible_and_does_not_generate_a_qr_code(): void
    {
        $member = $this->createUser();
        $this->createMembership($member, ['is_active' => false]);

        $component = Livewire::actingAs($member)
            ->test('pages::dashboard.member.absensi')
            ->assertSet('selectedMembershipId', null)
            ->assertSee('Tidak Ada Paket Aktif');

        $this->assertFalse($component->viewData('hasActivePackage'));
        $this->assertTrue($component->viewData('activeMemberships')->isEmpty());
        $this->assertNull($component->viewData('qrCode'));
    }

    public function test_future_membership_is_not_eligible_and_does_not_generate_a_qr_code(): void
    {
        $member = $this->createUser();
        $this->createMembership($member, [
            'start_date' => today()->addDay()->toDateString(),
            'membership_end_date' => today()->addMonth()->toDateString(),
        ]);

        $component = Livewire::actingAs($member)
            ->test('pages::dashboard.member.absensi')
            ->assertSet('selectedMembershipId', null)
            ->assertSee('Tidak Ada Paket Aktif');

        $this->assertFalse($component->viewData('hasActivePackage'));
        $this->assertTrue($component->viewData('activeMemberships')->isEmpty());
        $this->assertNull($component->viewData('qrCode'));
    }

    /** @param array<string, mixed> $attributes */
    private function createUser(array $attributes = []): User
    {
        return User::factory()->create([
            'role' => 'member',
            ...$attributes,
        ]);
    }

    /** @param array<string, mixed> $attributes */
    private function createMembership(User $owner, array $attributes = []): Membership
    {
        return Membership::create([
            'user_id' => $owner->id,
            'type' => 'membership',
            'base_price' => 300000,
            'discount_applied' => 0,
            'price_paid' => 300000,
            'total_paid' => 300000,
            'payment_status' => 'paid',
            'start_date' => today()->subDay()->toDateString(),
            'membership_end_date' => today()->addMonth()->toDateString(),
            'status' => 'active',
            'is_active' => true,
            ...$attributes,
        ]);
    }

    /** @param array<string, mixed> $attributes */
    private function createBooking(Membership $membership, User $member, array $attributes = []): PtBooking
    {
        return PtBooking::create([
            'membership_id' => $membership->id,
            'member_id' => $member->id,
            'pt_id' => $membership->pt_id,
            'booking_date' => today()->toDateString(),
            'booking_time' => '08:00:00',
            'status' => 'approved',
            'type' => 'fleksibel',
            'attendance' => 'not_yet',
            'is_free' => false,
            'is_paid' => false,
            ...$attributes,
        ]);
    }
}
