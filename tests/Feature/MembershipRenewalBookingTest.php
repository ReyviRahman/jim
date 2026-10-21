<?php

namespace Tests\Feature;

use App\Actions\CreateKeepPtBookings;
use App\Models\GymPackage;
use App\Models\Membership;
use App\Models\PtBooking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MembershipRenewalBookingTest extends TestCase
{
    use RefreshDatabase;

    private User $headCoach;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 19)->startOfDay());
        $this->headCoach = User::factory()->headCoach()->create();
    }

    public static function oldPackageStates(): array
    {
        return [
            'completed inactive' => ['completed', false],
            'completed active' => ['completed', true],
            'active inactive' => ['active', false],
            'active expired' => ['active', true],
        ];
    }

    #[DataProvider('oldPackageStates')]
    public function test_expired_renewed_package_is_selectable_and_can_be_booked(string $status, bool $active): void
    {
        [$old, $renewal] = $this->memberships(['status' => $status, 'is_active' => $active]);
        $before = $old->getAttributes();
        $page = $this->page($old);
        $this->assertContains($old->id, $page->get('filteredMemberships')->modelKeys());

        $page->call('saveInsertBooking')->assertHasNoErrors();
        $booking = PtBooking::sole();
        $this->assertSame($old->id, $booking->membership_id);
        $this->assertSame($before, $old->refresh()->getAttributes());
        Livewire::actingAs(User::factory()->create(['role' => 'admin']))
            ->test('pages::dashboard.admin.booking-jadwal.index')
            ->call('markAsAttended', $booking->id);
        $this->assertSame(2, $old->refresh()->remaining_sessions);
        $this->assertSame(8, $renewal->refresh()->remaining_sessions);
        $this->assertSame($status, $old->status);
        $this->assertSame($active, $old->is_active);
        $this->assertSame('2026-10-01', $old->pt_end_date->toDateString());
    }

    public static function invalidPackages(): array
    {
        return [
            'zero' => [['remaining_sessions' => 0]],
            'null' => [['remaining_sessions' => null]],
            'negative' => [['remaining_sessions' => -1]],
            'pending' => [['status' => 'pending']],
            'rejected' => [['status' => 'rejected']],
            'no coach' => [['pt_id' => null]],
            'no pt package' => [['pt_package_id' => null]],
            'no successor' => [['renewal_from' => null]],
        ];
    }

    #[DataProvider('invalidPackages')]
    public function test_invalid_packages_are_hidden_and_rejected_in_both_booking_modes(array $attributes): void
    {
        [$old] = $this->memberships($attributes);
        $page = $this->page($old);
        $this->assertNotContains($old->id, $page->get('filteredMemberships')->modelKeys());
        $page->call('saveInsertBooking')->assertHasErrors('insertBooking');
        $result = app(CreateKeepPtBookings::class)->execute($old->id, (int) $page->get('insertPtId'), today(), ['senin' => '09:00'], 'approved', 'regular');
        $this->assertSame('invalid_membership', $result['stop_reason']);
        $this->assertDatabaseCount('pt_bookings', 0);
    }

    public function test_server_rechecks_successor_after_selection(): void
    {
        [$old, $renewal] = $this->memberships();
        $page = $this->page($old);
        $this->assertContains($old->id, $page->get('filteredMemberships')->modelKeys());
        $renewal->update(['is_renewal' => false]);
        $page->call('saveInsertBooking')->assertHasErrors('insertBooking');
        $result = app(CreateKeepPtBookings::class)->execute($old->id, $old->pt_id, today(), ['senin' => '09:00'], 'approved', 'regular');
        $this->assertSame('invalid_membership', $result['stop_reason']);
        $this->assertDatabaseCount('pt_bookings', 0);
    }

    public function test_keep_ignores_old_expiry_but_respects_reserved_capacity_and_skips_duplicates(): void
    {
        [$old, $renewal] = $this->memberships();
        $page = $this->page($old);
        $page->call('saveInsertBooking')->assertHasNoErrors();
        $this->page($old)->set('insertType', 'keep')
            ->assertSet('insertWeekStart', '2026-10-19')
            ->set('insertSelectedDays', ['senin'])
            ->set('insertDayTimes', ['senin' => '09:00'])
            ->call('saveInsertBooking')->assertHasNoErrors();

        $this->assertSame(['2026-10-19', '2026-10-26', '2026-11-02'],
            PtBooking::whereBelongsTo($old)->orderBy('booking_date')->get()->map(fn (PtBooking $booking): string => $booking->booking_date->toDateString())->all());
        $this->assertSame(3, $old->refresh()->remaining_sessions);
        $this->assertSame(8, $renewal->refresh()->remaining_sessions);
        $result = app(CreateKeepPtBookings::class)->execute($old->id, $old->pt_id, today(), ['senin' => '09:00'], 'approved', 'regular');
        $this->assertSame('no_capacity', $result['stop_reason']);
        $this->page($old)->set('insertTime', '11:00')->call('saveInsertBooking')->assertSet('showInsertErrorModal', true);
        $this->assertDatabaseCount('pt_bookings', 3);
    }

    public function test_future_start_is_still_hidden_and_deleting_successor_clears_link(): void
    {
        [$old, $renewal] = $this->memberships(['start_date' => '2026-11-01']);
        $this->assertNotContains($old->id, $this->page($old)->get('filteredMemberships')->modelKeys());
        $renewal->delete();
        $this->assertNull($old->refresh()->renewal_from);
        $this->assertSame(3, $old->remaining_sessions);
    }

    private function page(Membership $old): Testable
    {
        return Livewire::actingAs($this->headCoach)
            ->test('pages::dashboard.admin.booking-jadwal.index')
            ->call('openInsertModal', 'senin', 9)
            ->set('insertMembershipId', $old->id)
            ->set('insertPtId', $old->pt_id ?? User::factory()->create(['role' => 'pt'])->id)
            ->set('insertType', 'fleksibel')->set('studioType', 'regular');
    }

    /** @return array{Membership, Membership} */
    private function memberships(array $attributes = []): array
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
            'membership_end_date' => '2026-10-01', 'remaining_sessions' => 3, 'total_sessions' => 10,
        ]);
        $renewal = Membership::create([
            'user_id' => $old->user_id, 'type' => 'membership', 'is_renewal' => true,
            'base_price' => 300000, 'price_paid' => 300000, 'total_paid' => 0,
            'payment_status' => 'unpaid', 'status' => 'pending', 'is_active' => false,
            'membership_end_date' => '2026-10-02', 'remaining_sessions' => 8,
        ]);
        $old->update(array_replace(['renewal_from' => $renewal->id], $attributes));

        return [$old->refresh(), $renewal];
    }
}
