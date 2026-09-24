<?php

namespace Tests\Feature;

use App\Actions\CreateKeepPtBookings;
use App\Actions\CreateMemberPtBooking;
use App\Models\Membership;
use App\Models\PtBooking;
use App\Models\User;
use App\PtStudioBooking;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PtStudioBookingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-09-21 06:00:00'));
    }

    #[DataProvider('prices')]
    public function test_membership_price_controls_private_eligibility(?int $normal, int $paid, bool $allowed): void
    {
        $membership = $this->membership(['normal_price' => $normal, 'price_paid' => $paid]);
        $this->assertSame($allowed, $membership->hasNormalPrice());
        $this->assertSame($allowed, ($membership->getPriceLabel()['label'] ?? null) === 'Harga Normal');
        if (! $allowed) {
            $this->expectException(ValidationException::class);
        }
        $booking = app(CreateMemberPtBooking::class)->execute($membership->user, $membership->id, '2026-09-22', '09:00', 'private_studio');
        $this->assertSame('private_studio', $booking->studio_type);
    }

    public static function prices(): array
    {
        return [[300000, 300000, true], [300000, 400000, true], [300000, 200000, false], [300000, 100000, false], [null, 300000, false], [0, 300000, false]];
    }

    public function test_third_private_booking_is_rejected_across_coaches(): void
    {
        foreach (range(1, 2) as $i) {
            $membership = $this->membership();
            app(CreateMemberPtBooking::class)->execute($membership->user, $membership->id, '2026-09-22', '09:00', 'private_studio');
        }
        $membership = $this->membership();
        $this->expectException(ValidationException::class);
        app(CreateMemberPtBooking::class)->execute($membership->user, $membership->id, '2026-09-22', '09:00', 'private_studio');
    }

    #[DataProvider('intervals')]
    public function test_capacity_uses_peak_simultaneous_occupancy(array $starts, string $candidate, bool $allowed): void
    {
        $membership = $this->membership();
        foreach ($starts as $start) {
            $this->booking($membership, $start);
        }
        if (! $allowed) {
            $this->expectException(ValidationException::class);
        }
        app(PtStudioBooking::class)->validate($membership, 'private_studio', Carbon::parse($candidate));
        $this->assertTrue($allowed);
    }

    public static function intervals(): array
    {
        return [
            [['2026-09-22 08:30', '2026-09-22 09:30'], '2026-09-22 09:00', true],
            [['2026-09-22 08:30', '2026-09-22 09:00'], '2026-09-22 09:00', false],
            [['2026-09-22 08:00', '2026-09-22 08:00'], '2026-09-22 09:00', true],
            [['2026-09-21 23:30', '2026-09-22 00:00'], '2026-09-22 00:00', false],
            [['2026-09-22 00:00', '2026-09-22 00:00'], '2026-09-21 23:30', false],
        ];
    }

    #[DataProvider('statuses')]
    public function test_only_active_private_bookings_use_capacity(string $status, ?string $studio, bool $cancelRequest, bool $allowed): void
    {
        $membership = $this->membership();
        foreach (range(1, 2) as $i) {
            $this->booking($membership, '2026-09-22 09:00', ['status' => $status, 'studio_type' => $studio, 'cancellation_requested_at' => $cancelRequest ? now() : null]);
        }
        if (! $allowed) {
            $this->expectException(ValidationException::class);
        }
        app(PtStudioBooking::class)->validate($membership, 'private_studio', Carbon::parse('2026-09-22 09:00'));
        $this->assertTrue($allowed);
    }

    public static function statuses(): array
    {
        return [
            ['pending', 'private_studio', false, false], ['approved', 'private_studio', true, false],
            ['cancelled', 'private_studio', false, true], ['rejected', 'private_studio', false, true],
            ['approved', 'regular', false, true], ['approved', null, false, true],
        ];
    }

    public function test_regular_has_no_studio_capacity_limit_or_price_restriction(): void
    {
        $membership = $this->membership(['normal_price' => null]);
        foreach (range(1, 4) as $i) {
            $this->booking($membership, '2026-09-22 09:00');
        }
        app(PtStudioBooking::class)->validate($membership, 'regular', Carbon::parse('2026-09-22 09:00'));
        $this->assertTrue(true);
    }

    public function test_keep_rolls_back_all_new_bookings_on_later_conflict(): void
    {
        $membership = $this->membership();
        $other = $this->membership();
        $this->booking($other, '2026-09-29 09:00');
        $this->booking($other, '2026-09-29 09:00');
        try {
            app(CreateKeepPtBookings::class)->execute($membership->id, $membership->pt_id, Carbon::parse('2026-09-21'), ['selasa' => '09:00'], 'pending', 'private_studio');
            $this->fail('Expected full studio.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('29/09/2026 09:00', $exception->getMessage());
        }
        $this->assertSame(0, $membership->ptBookings()->count());
        $this->assertSame(2, PtBooking::count());
    }

    #[DataProvider('invalidOptions')]
    public function test_both_forms_require_a_valid_studio(string $value): void
    {
        $membership = $this->membership();
        Livewire::actingAs($membership->user)->test('pages::dashboard.member.jadwal-pt.index')
            ->call('openBookingModal', '2026-09-22', '09:00')
            ->set('studioType', $value)->call('book')->assertHasErrors('studioType');

        Livewire::actingAs(User::factory()->create(['role' => 'admin']))->test('pages::dashboard.admin.booking-jadwal.index')
            ->set('insertMembershipId', $membership->id)->set('insertPtId', $membership->pt_id)
            ->set('insertDate', '2026-09-22')->set('insertTime', '09:00')
            ->set('studioType', $value)->call('saveInsertBooking')->assertHasErrors('studioType');
        $this->assertSame(0, PtBooking::count());
    }

    public static function invalidOptions(): array
    {
        return [[''], ['invalid']];
    }

    public function test_admin_cannot_bypass_price_rule_even_for_free_sessions(): void
    {
        $membership = $this->membership(['price_paid' => 200000]);
        Livewire::actingAs(User::factory()->create(['role' => 'admin']))
            ->test('pages::dashboard.admin.booking-jadwal.index')
            ->set('insertMembershipId', $membership->id)->set('insertPtId', $membership->pt_id)
            ->set('insertDate', '2026-09-22')->set('insertTime', '09:00')
            ->set('insertIsFree', true)->set('studioType', 'private_studio')
            ->call('saveInsertBooking')->assertHasErrors('studioType');
        $this->assertSame(0, PtBooking::count());
    }

    public function test_admin_stores_and_displays_private_selection_and_resets_it_on_membership_change(): void
    {
        $membership = $this->membership();
        $page = Livewire::actingAs(User::factory()->create(['role' => 'admin']))
            ->test('pages::dashboard.admin.booking-jadwal.index')
            ->set('insertMembershipId', $membership->id)->set('insertPtId', $membership->pt_id)
            ->set('insertDate', '2026-09-22')->set('insertTime', '09:00')
            ->set('studioType', 'private_studio')
            ->call('saveInsertBooking')->assertHasNoErrors();
        $booking = PtBooking::sole();
        $this->assertSame('private_studio', $booking->studio_type);
        $page->call('openDetailModal', $booking->id)->assertSee('Private Studio');
        $page->set('studioType', 'private_studio')->call('selectMembership', $membership->id)->assertSet('studioType', '');
    }

    public function test_member_calendar_history_and_detail_display_studio_and_legacy_null(): void
    {
        $membership = $this->membership();
        $booking = $this->booking($membership, '2026-09-22 09:00');
        $page = Livewire::actingAs($membership->user)->test('pages::dashboard.member.jadwal-pt.index');
        $this->assertSame('Private Studio', $page->get('calendar')[1]['slots'][2]['ownBookings'][0]['studio']);
        $page->call('openDetailModal', $booking->id)->assertSee('Private Studio');
        $booking->update(['studio_type' => null]);
        $this->assertSame('—', $booking->fresh()->studioLabel());
        $page->call('closeDetailModal')->call('openDetailModal', $booking->id)->assertSee('—');
        $page->set('studioType', 'private_studio')->set('selectedMembershipId', $membership->id)->assertSet('studioType', '');
    }

    public function test_keep_saves_one_choice_for_the_entire_series(): void
    {
        $membership = $this->membership(['remaining_sessions' => 3]);
        $result = app(CreateKeepPtBookings::class)->execute($membership->id, $membership->pt_id, Carbon::parse('2026-09-21'), ['selasa' => '09:00'], 'pending', 'private_studio');
        $this->assertSame(3, $result['created_count']);
        $this->assertSame(3, $membership->ptBookings()->where('studio_type', 'private_studio')->count());
    }

    #[DataProvider('studioEdits')]
    public function test_edit_booking_validates_and_saves_studio(?string $initial, string $selected, int $price, int $occupied, ?string $error): void
    {
        $membership = $this->membership(['price_paid' => $price]);
        $booking = $this->booking($membership, '2026-09-22 09:00', ['studio_type' => $initial]);
        for ($index = 0; $index < $occupied; $index++) {
            $this->booking($this->membership(), '2026-09-22 09:00');
        }
        $page = Livewire::actingAs(User::factory()->create(['role' => 'admin']))
            ->test('pages::dashboard.admin.booking-jadwal.index')
            ->call('openChangeCoachModal', $booking->id)
            ->assertSet('editStudioType', $initial ?? '')
            ->set('editStudioType', $selected)
            ->call('saveChangeCoach');
        if ($error !== null) {
            $page->assertHasErrors('editStudioType')->assertSee($error);
            $this->assertSame($initial, $booking->fresh()->studio_type);
        } else {
            $page->assertHasNoErrors()->assertSet('showChangeCoachModal', false);
            $this->assertSame($selected === '' ? null : $selected, $booking->fresh()->studio_type);
        }
    }

    public static function studioEdits(): array
    {
        return [
            ['regular', 'private_studio', 300000, 1, null],
            ['regular', 'private_studio', 300000, 2, 'Private Studio penuh'],
            ['regular', 'private_studio', 200000, 0, 'Private Studio hanya tersedia'],
            ['private_studio', 'regular', 200000, 1, null],
            ['private_studio', 'private_studio', 300000, 1, null],
            [null, '', 200000, 0, null],
            [null, 'private_studio', 300000, 1, null],
            ['regular', '', 300000, 0, 'Pilih Private Studio atau Regular.'],
            ['regular', 'invalid', 300000, 0, 'Pilihan studio tidak valid.'],
        ];
    }

    private function membership(array $attributes = []): Membership
    {
        return Membership::create([
            'user_id' => User::factory()->create(['role' => 'member'])->id,
            'pt_id' => User::factory()->create(['role' => 'pt'])->id,
            'type' => 'pt', 'base_price' => 300000, 'normal_price' => 300000, 'net_price' => 200000,
            'price_paid' => 300000, 'total_paid' => 300000, 'payment_status' => 'paid',
            'start_date' => '2026-09-01', 'pt_end_date' => '2026-10-21',
            'status' => 'active', 'is_active' => true, 'total_sessions' => 10, 'remaining_sessions' => 10,
            ...$attributes,
        ]);
    }

    private function booking(Membership $membership, string $start, array $attributes = []): PtBooking
    {
        return PtBooking::create([
            'membership_id' => $membership->id, 'member_id' => $membership->user_id, 'pt_id' => $membership->pt_id,
            'booking_date' => Carbon::parse($start)->toDateString(), 'booking_time' => Carbon::parse($start)->format('H:i:s'),
            'status' => 'approved', 'studio_type' => 'private_studio', 'type' => 'fleksibel', 'attendance' => 'not_yet',
            ...$attributes,
        ]);
    }
}
