<?php

namespace Tests\Feature;

use App\Actions\CreateMemberPtBooking;
use App\Models\GymPackage;
use App\Models\Membership;
use App\Models\PtBooking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class MemberRenewalBookingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 19)->setTime(8, 0));
    }

    public static function eligibleStates(): array
    {
        return [['completed', false], ['completed', true], ['active', false], ['active', true]];
    }

    #[DataProvider('eligibleStates')]
    public function test_renewed_old_package_is_available_and_booking_does_not_change_it(string $status, bool $active): void
    {
        $old = $this->membership(['status' => $status, 'is_active' => $active]);
        $before = $old->getAttributes();
        $page = Livewire::actingAs($old->user)->test('pages::dashboard.member.jadwal-pt.index');
        $this->assertContains($old->id, $page->get('memberships')->modelKeys());
        $this->assertTrue($page->get('memberships')->first()->relationLoaded('renewalSuccessor'));
        $page->call('openBookingModal', '2026-10-20', '09:00')
            ->assertSet('showBookingModal', true)->assertSet('selectedMembershipId', $old->id)
            ->set('studioType', 'regular')->call('book')->assertHasNoErrors();
        $booking = PtBooking::sole();
        $this->assertSame($old->id, $booking->membership_id);
        $this->assertSame('pending', $booking->status);
        $this->assertSame($before, $old->refresh()->getAttributes());
    }

    public static function ineligibleStates(): array
    {
        return [
            [['renewal_from' => null]], [['status' => 'pending']], [['status' => 'rejected']],
            [['remaining_sessions' => 0]], [['remaining_sessions' => null]], [['remaining_sessions' => -1]],
            [['pt_id' => null]], [['pt_package_id' => null]],
        ];
    }

    #[DataProvider('ineligibleStates')]
    public function test_ineligible_old_package_is_hidden_and_rejected(array $attributes): void
    {
        $old = $this->membership($attributes);
        $page = Livewire::actingAs($old->user)->test('pages::dashboard.member.jadwal-pt.index');
        $this->assertNotContains($old->id, $page->get('memberships')->modelKeys());
        $this->expectException(ValidationException::class);
        $this->book($old);
    }

    public function test_successor_changed_after_opening_modal_is_rechecked(): void
    {
        $old = $this->membership();
        $page = Livewire::actingAs($old->user)->test('pages::dashboard.member.jadwal-pt.index')
            ->call('openBookingModal', '2026-10-20', '09:00')->assertSet('showBookingModal', true);
        $old->renewalSuccessor->update(['is_renewal' => false]);
        $page->set('studioType', 'regular')->call('book')->assertHasErrors('booking');
        $this->assertDatabaseCount('pt_bookings', 0);
    }

    public function test_shared_member_can_book_and_history_and_cancellation_remain_available_without_renewal(): void
    {
        $old = $this->membership();
        $member = User::factory()->create(['role' => 'member']);
        $old->members()->attach($member->id);
        $booking = app(CreateMemberPtBooking::class)->execute($member, $old->id, '2026-10-20', '09:00', 'regular');
        $old->update(['renewal_from' => null]);
        $page = Livewire::actingAs($member)->test('pages::dashboard.member.jadwal-pt.index');
        $this->assertContains($booking->id, $page->get('history')->modelKeys());
        $page->call('openCancelModal', $booking->id)->set('cancelReason', 'Tidak bisa datang')
            ->call('cancelBooking')->assertHasNoErrors();
        $this->assertSame('cancelled', $booking->refresh()->status);
    }

    public function test_other_member_cannot_book_renewed_package(): void
    {
        $old = $this->membership();
        $this->expectException(HttpException::class);
        app(CreateMemberPtBooking::class)->execute(User::factory()->create(['role' => 'member']), $old->id, '2026-10-20', '09:00', 'regular');
    }

    public function test_bundle_remains_excluded(): void
    {
        $old = $this->membership(['type' => 'bundle_pt_membership']);
        $page = Livewire::actingAs($old->user)->test('pages::dashboard.member.jadwal-pt.index');
        $this->assertNotContains($old->id, $page->get('memberships')->modelKeys());
        $this->expectException(HttpException::class);
        $this->book($old);
    }

    public static function invalidDates(): array
    {
        return [['2026-10-19', '07:00', null], ['2026-10-27', '09:00', null], ['2026-10-20', '09:00', '2026-10-21']];
    }

    #[DataProvider('invalidDates')]
    public function test_renewal_does_not_bypass_booking_window_or_start_date(string $date, string $time, ?string $start): void
    {
        $old = $this->membership($start === null ? [] : ['start_date' => $start]);
        $this->expectException(ValidationException::class);
        $this->book($old, $date, $time);
    }

    public function test_pending_reservations_still_limit_sessions(): void
    {
        $old = $this->membership(['remaining_sessions' => 1]);
        $this->book($old);
        $this->expectException(ValidationException::class);
        $this->book($old, '2026-10-21');
    }

    public function test_only_one_booking_per_day_is_allowed(): void
    {
        $old = $this->membership();
        $this->book($old);
        $this->expectException(ValidationException::class);
        $this->book($old, '2026-10-20', '11:00');
    }

    public function test_coach_conflicts_are_still_rejected(): void
    {
        $first = $this->membership();
        $second = $this->membership(['pt_id' => $first->pt_id]);
        $this->book($first);
        $this->expectException(ValidationException::class);
        $this->book($second);
    }

    private function book(Membership $old, string $date = '2026-10-20', string $time = '09:00'): PtBooking
    {
        return app(CreateMemberPtBooking::class)->execute($old->user, $old->id, $date, $time, 'regular');
    }

    private function membership(array $attributes = []): Membership
    {
        $package = GymPackage::create([
            'type' => 'pt', 'name' => 'PT', 'category' => 'single', 'max_members' => 1,
            'price' => 300000, 'discount' => 0, 'is_active' => true,
        ]);
        $old = Membership::create([
            'user_id' => User::factory()->create(['role' => 'member'])->id,
            'pt_id' => User::factory()->create(['role' => 'pt'])->id,
            'pt_package_id' => $package->id, 'type' => 'pt',
            'base_price' => 300000, 'price_paid' => 300000, 'total_paid' => 300000,
            'payment_status' => 'paid', 'status' => 'completed', 'is_active' => false,
            'start_date' => '2026-09-01', 'pt_end_date' => '2026-10-01',
            'remaining_sessions' => 3, 'total_sessions' => 10,
        ]);
        $successor = Membership::create([
            'user_id' => $old->user_id, 'type' => 'membership', 'is_renewal' => true,
            'base_price' => 300000, 'price_paid' => 300000, 'total_paid' => 0,
            'payment_status' => 'unpaid', 'status' => 'pending', 'is_active' => false,
            'membership_end_date' => '2026-10-02',
        ]);
        $old->update(array_replace(['renewal_from' => $successor->id], $attributes));

        return $old->refresh();
    }
}
