<?php

namespace Tests\Feature;

use App\Actions\CancelMemberPtBooking;
use App\Actions\CreateMemberPtBooking;
use App\Models\Membership;
use App\Models\PtBooking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class MemberPtScheduleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 9, 21)->setTime(6, 0));
    }

    public function test_calendar_has_sixteen_slots_each_day_and_navigates_weeks(): void
    {
        $membership = $this->membership();
        $page = $this->page($membership)->assertSee('07:00 - 08:00')->assertSee('22:00 - 23:00');
        $calendar = $page->get('calendar');
        $this->assertCount(7, $calendar);
        foreach ($calendar as $day) {
            $this->assertCount(16, $day['slots']);
        }
        $page->call('nextWeek')->assertSet('weekStart', '2026-09-28')
            ->call('previousWeek')->assertSet('weekStart', '2026-09-21')
            ->call('previousWeek')->call('thisWeek')->assertSet('weekStart', '2026-09-21');
    }

    public function test_latest_active_package_is_selected_and_switching_changes_coach_availability(): void
    {
        $older = $this->membership(['start_date' => '2026-09-01']);
        $latest = $this->membership(['user_id' => $older->user_id, 'start_date' => '2026-09-20']);
        $this->membership(['user_id' => $older->user_id, 'start_date' => '2026-09-21', 'is_active' => false]);
        $this->booking($this->membership(['pt_id' => $latest->pt_id]));
        $page = $this->page($older)->call('setDayView', 'all')->assertSet('selectedMembershipId', $latest->id)->assertSee('Dibooking member lain');
        $this->assertTrue($page->get('calendar')[1]['slots'][0]['occupied']);
        $page->set('selectedMembershipId', $older->id);
        $this->assertFalse($page->get('calendar')[1]['slots'][0]['occupied']);
    }

    #[DataProvider('unselectableMemberships')]
    public function test_only_active_memberships_can_be_selected(array $attributes): void
    {
        $active = $this->membership();
        $inactive = $this->membership(['user_id' => $active->user_id, ...$attributes]);
        $groupMembership = $this->membership($attributes);
        $groupMembership->members()->attach($active->user);

        $page = $this->page($active)->assertSet('selectedMembershipId', $active->id);

        $this->assertSame([$active->id], $page->get('memberships')->modelKeys());
        $page->set('selectedMembershipId', $inactive->id)->assertForbidden();
        $this->page($active)->set('selectedMembershipId', $groupMembership->id)->assertForbidden();
    }

    public static function unselectableMemberships(): array
    {
        return [
            [['status' => 'pending']],
            [['status' => 'rejected']],
            [['status' => 'completed']],
            [['is_active' => false]],
        ];
    }

    public function test_member_with_only_nonactive_memberships_has_no_package_selected(): void
    {
        $membership = $this->membership(['status' => 'completed']);

        $page = $this->page($membership)
            ->assertSet('selectedMembershipId', null)
            ->assertSee('Tidak ada membership PT');

        $this->assertCount(0, $page->get('memberships'));
    }

    public function test_daily_navigation_and_date_picker_keep_the_week_in_sync(): void
    {
        $page = $this->page($this->membership())
            ->call('setDayView', 'today')->assertSet('dayView', 'today')
            ->call('previousDay')->assertSet('dateFrom', '2026-09-20')->assertSet('weekStart', '2026-09-14')
            ->call('nextDay')->assertSet('dateFrom', '2026-09-21')->assertSet('weekStart', '2026-09-21')
            ->set('dateFrom', '2026-10-04')->assertSet('weekStart', '2026-09-28')
            ->assertSee('Minggu, 4 Oktober 2026')
            ->call('nextDay')->assertSet('weekStart', '2026-10-05')
            ->call('today')->assertSet('dateFrom', '2026-09-21')
            ->call('setDayView', 'all')->assertSet('dayView', 'all');

        $page->set('dateFrom', '2026-02-30')->assertHasErrors('dateFrom');
    }

    public function test_member_calendar_uses_shared_admin_table_layout_and_date_controls(): void
    {
        $this->page($this->membership())
            ->assertSeeHtml('data-member-pt-calendar')
            ->assertDontSeeHtml('data-booking-schedule')
            ->assertDontSeeHtml('data-responsive-table')
            ->assertSee('Pilih tanggal jadwal')
            ->call('setDayView', 'today')->assertSee('Kemarin')->assertSee('Besok')
            ->assertDontSee('Pilih hari');
    }

    public function test_calendar_shows_selected_day_by_default_and_can_switch_to_weekly_view(): void
    {
        $page = $this->page($this->membership(), weekly: false)->assertSet('dayView', 'today')
            ->assertSeeHtml('data-date="2026-09-21"')
            ->assertDontSeeHtml('data-date="2026-09-22"')
            ->call('setDayView', 'all');

        foreach (range(21, 27) as $day) {
            $page->assertSeeHtml('data-date="2026-09-'.$day.'"');
        }

        $page->call('setDayView', 'today')
            ->assertSeeHtml('data-date="2026-09-21"')
            ->assertDontSeeHtml('data-date="2026-09-22"')
            ->call('nextDay')
            ->assertSeeHtml('data-date="2026-09-22"')
            ->assertDontSeeHtml('data-date="2026-09-21"')
            ->call('setDayView', 'all')
            ->assertSeeHtml('data-date="2026-09-21"')
            ->assertSeeHtml('data-date="2026-09-27"');
    }

    public function test_daily_booking_count_counts_unique_active_bookings_of_the_selected_package(): void
    {
        $membership = $this->membership();
        $this->booking($membership, ['booking_time' => '07:30:00', 'status' => 'pending']);
        $this->booking($membership, ['booking_time' => '10:00:00']);
        $this->booking($membership, ['booking_time' => '12:00:00', 'cancellation_requested_at' => now()]);
        $this->booking($membership, ['booking_time' => '14:00:00', 'status' => 'cancelled']);
        $this->booking($membership, ['booking_time' => '15:00:00', 'status' => 'rejected']);
        $this->booking($membership, ['booking_date' => '2026-09-23']);
        $otherPackage = $this->membership(['user_id' => $membership->user_id, 'pt_id' => $membership->pt_id]);
        $this->booking($otherPackage, ['booking_time' => '16:00:00']);
        $this->booking($this->membership(['pt_id' => $membership->pt_id]), ['booking_time' => '18:00:00']);

        $page = $this->page($membership)->set('selectedMembershipId', $membership->id);
        $calendar = $page->get('calendar');
        $this->assertSame(0, $calendar[0]['bookingCount']);
        $this->assertSame(3, $calendar[1]['bookingCount']);
        $this->assertSame(1, $calendar[2]['bookingCount']);

        $page->set('selectedMembershipId', $otherPackage->id);
        $this->assertSame(1, $page->get('calendar')[1]['bookingCount']);
    }

    public function test_member_submits_pending_booking_without_decrementing_sessions(): void
    {
        $membership = $this->membership();
        $this->page($membership)->call('openBookingModal', '2026-09-22', '07:00')
            ->assertSet('showBookingModal', true)->assertSee('Konfirmasi booking PT')
            ->call('book')->assertHasNoErrors()->assertSet('showBookingModal', false)
            ->assertSee('Booking Saya')->assertSee('Pending');
        $this->assertDatabaseHas('pt_bookings', [
            'membership_id' => $membership->id, 'member_id' => $membership->user_id,
            'pt_id' => $membership->pt_id, 'status' => 'pending', 'type' => 'fleksibel',
            'attendance' => 'not_yet', 'is_free' => false, 'booking_time' => '07:00:00',
        ]);
        $this->assertSame(10, $membership->fresh()->remaining_sessions);
    }

    public function test_group_member_can_book_and_open_shared_booking(): void
    {
        $membership = $this->membership();
        $member = User::factory()->create(['role' => 'member']);
        $membership->members()->attach($member);
        $page = Livewire::actingAs($member)->test('pages::dashboard.member.jadwal-pt.index');
        $page->call('openBookingModal', '2026-09-22', '07:00')->call('book')->assertHasNoErrors();
        $booking = PtBooking::sole();
        $this->assertSame($membership->user_id, $booking->member_id);
        $page->call('openDetailModal', $booking->id)->assertSee($membership->user->name);
    }

    public function test_other_members_booking_is_private_even_when_history_is_filtered(): void
    {
        $membership = $this->membership();
        $other = $this->membership(['pt_id' => $membership->pt_id]);
        $other->user->update(['name' => 'Private Other Member']);
        $booking = $this->booking($other, ['notes' => 'Private session note']);
        $page = $this->page($membership)->assertSee('Dibooking member lain')->assertDontSee('Private Other Member')->assertDontSee('Private session note');
        $page->set('statusFilter', 'cancelled')->assertSee('Dibooking member lain');
        $this->assertSame([], $page->get('calendar')[1]['slots'][0]['ownBookings']);
        $snapshot = json_encode($page->snapshot);
        $this->assertStringNotContainsString('Private Other Member', $snapshot);
        $this->assertStringNotContainsString('Private session note', $snapshot);
        $page->call('openDetailModal', $booking->id)->assertNotFound();
    }

    public function test_foreign_membership_cannot_be_selected(): void
    {
        $membership = $this->membership();
        $foreign = $this->membership();
        $this->page($membership)->set('selectedMembershipId', $foreign->id)->assertForbidden();
    }

    public function test_foreign_membership_cannot_be_booked_directly(): void
    {
        $membership = $this->membership();
        $foreign = $this->membership();
        $this->expectException(HttpException::class);
        app(CreateMemberPtBooking::class)->execute($membership->user, $foreign->id, '2026-09-22', '07:00');
    }

    #[DataProvider('blockingStatuses')]
    public function test_active_booking_blocks_member_submission(string $status, bool $pendingCancel): void
    {
        $membership = $this->membership();
        $this->booking($this->membership(['pt_id' => $membership->pt_id]), [
            'status' => $status, 'cancellation_requested_at' => $pendingCancel ? now() : null,
        ]);
        $this->page($membership)->call('openBookingModal', '2026-09-22', '07:00')->assertHasErrors('booking');
        $this->expectException(ValidationException::class);
        $this->submit($membership);
    }

    public static function blockingStatuses(): array
    {
        return [['pending', false], ['approved', false], ['approved', true]];
    }

    #[DataProvider('nonBlockingStatuses')]
    public function test_cancelled_or_rejected_booking_does_not_block(string $status): void
    {
        $membership = $this->membership();
        $this->booking($this->membership(['pt_id' => $membership->pt_id]), ['status' => $status]);
        $slot = $this->page($membership)->get('calendar')[1]['slots'][0];
        $this->assertSame([], $slot['ownBookings']);
        $this->assertFalse($slot['occupied']);
        $this->assertFalse($slot['otherBooked']);
        $this->assertModelExists($this->submit($membership));
    }

    #[DataProvider('nonBlockingStatuses')]
    public function test_own_cancelled_or_rejected_card_remains_visible_without_holding_slot(string $status): void
    {
        $membership = $this->membership();
        $booking = $this->booking($membership, ['status' => $status]);
        $page = $this->page($membership);
        $slot = $page->get('calendar')[1]['slots'][0];

        $this->assertSame([['id' => $booking->id, 'status' => ucfirst($status)]], $slot['ownBookings']);
        $this->assertFalse($slot['occupied']);
        $this->assertFalse($slot['otherBooked']);
        $page->call('openDetailModal', $booking->id)->assertSet('selectedBookingId', $booking->id);
        $this->assertModelExists($this->submit($membership));
    }

    public function test_own_cancelled_card_does_not_hide_another_members_active_booking(): void
    {
        $membership = $this->membership();
        $booking = $this->booking($membership, ['status' => 'cancelled']);
        $this->booking($this->membership(['pt_id' => $membership->pt_id]));
        $page = $this->page($membership)->assertSee('Dibooking member lain');
        $slot = $page->get('calendar')[1]['slots'][0];

        $this->assertSame([['id' => $booking->id, 'status' => 'Cancelled']], $slot['ownBookings']);
        $this->assertTrue($slot['occupied']);
        $this->assertTrue($slot['otherBooked']);
    }

    public static function nonBlockingStatuses(): array
    {
        return [['cancelled'], ['rejected']];
    }

    public function test_another_coach_can_be_booked_at_the_same_time(): void
    {
        $membership = $this->membership();
        $this->booking($this->membership());
        $this->assertModelExists($this->submit($membership));
    }

    public function test_half_hour_booking_blocks_both_overlapping_slots_but_not_adjacent_slot(): void
    {
        $membership = $this->membership();
        $this->booking($this->membership(['pt_id' => $membership->pt_id]), ['booking_time' => '07:30:00']);
        $page = $this->page($membership);
        $slots = $page->get('calendar')[1]['slots'];
        $this->assertTrue($slots[0]['occupied']);
        $this->assertTrue($slots[1]['occupied']);
        $this->assertFalse($slots[2]['occupied']);
        foreach (['07:00', '08:00'] as $time) {
            try {
                $this->submit($membership, $time);
                $this->fail('Overlapping booking was accepted.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('booking', $exception->errors());
            }
        }
        $this->assertModelExists($this->submit($membership, '09:00'));
        $this->assertModelExists($this->submit($this->membership(['pt_id' => $membership->pt_id]), '10:00'));
    }

    public function test_slot_taken_after_confirmation_opens_is_rejected_and_refreshed(): void
    {
        $membership = $this->membership();
        $page = $this->page($membership)->call('openBookingModal', '2026-09-22', '07:00');
        $this->booking($this->membership(['pt_id' => $membership->pt_id]));
        $page->call('book')->assertHasErrors('booking')->assertSet('showBookingModal', false)->assertSee('Dibooking member lain');
        $this->assertDatabaseCount('pt_bookings', 1);
    }

    public function test_repeated_submission_does_not_create_duplicate(): void
    {
        $membership = $this->membership();
        $this->submit($membership);
        try {
            $this->submit($membership);
            $this->fail('Duplicate accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('booking', $exception->errors());
        }
        $this->assertDatabaseCount('pt_bookings', 1);
    }

    #[DataProvider('invalidTimes')]
    public function test_invalid_hour_or_date_is_rejected(string $date, string $time): void
    {
        $this->expectException(ValidationException::class);
        $this->submit($this->membership(), $time, $date);
    }

    public static function invalidTimes(): array
    {
        return [['2026-09-22', '06:00'], ['2026-09-22', '23:00'], ['2026-09-22', '07:30'], ['2026-09-22', '07:00:01'], ['2026-02-30', '07:00'], ['invalid', '07:00'], ['2026-09-20', '07:00']];
    }

    public function test_last_slot_is_bookable(): void
    {
        $this->assertSame('22:00', $this->submit($this->membership(), '22:00')->booking_time->format('H:i'));
    }

    #[DataProvider('ineligibleMemberships')]
    public function test_ineligible_package_cannot_book(array $attributes): void
    {
        $this->expectException(ValidationException::class);
        $this->submit($this->membership($attributes));
    }

    public static function ineligibleMemberships(): array
    {
        return [[['is_active' => false]], [['status' => 'pending']], [['pt_id' => null]], [['remaining_sessions' => 0]], [['start_date' => '2026-09-23']], [['pt_end_date' => '2026-09-20']]];
    }

    public function test_reserved_capacity_is_shared_and_cancelled_or_free_sessions_do_not_consume_it(): void
    {
        $membership = $this->membership(['remaining_sessions' => 2]);
        $this->booking($membership, ['booking_time' => '10:00:00', 'status' => 'pending']);
        $this->booking($membership, ['booking_time' => '11:00:00', 'status' => 'cancelled']);
        $this->booking($membership, ['booking_time' => '12:00:00', 'is_free' => true]);
        $this->booking($membership, ['booking_time' => '13:00:00', 'attendance' => 'attended']);
        $this->assertModelExists($this->submit($membership, '07:00', '2026-09-23'));
        $this->expectException(ValidationException::class);
        $this->submit($membership, '08:00');
    }

    public function test_approved_not_yet_bookings_do_not_reserve_quota_but_still_occupy_the_coach(): void
    {
        $membership = $this->membership(['remaining_sessions' => 1]);
        foreach (['10:00:00', '11:00:00', '12:00:00', '13:00:00'] as $time) {
            $this->booking($membership, ['booking_time' => $time, 'status' => 'approved', 'attendance' => 'not_yet']);
        }

        $page = $this->page($membership);
        $this->assertNull($page->get('unavailableReason'));
        $this->assertTrue($page->get('calendar')[1]['slots'][3]['occupied']);
        $page->call('openBookingModal', '2026-09-23', '07:00')
            ->assertSet('showBookingModal', true)->call('book')->assertHasNoErrors();

        $this->assertDatabaseCount('pt_bookings', 5);
        $this->assertSame(1, $membership->fresh()->remaining_sessions);
    }

    public function test_end_date_is_inclusive_and_next_day_is_rejected(): void
    {
        $membership = $this->membership(['pt_end_date' => '2026-09-22']);
        $this->assertModelExists($this->submit($membership, '22:00'));
        $this->expectException(ValidationException::class);
        $this->travelTo(now()->addDay());
        $this->submit($membership, '07:00', '2026-09-23');
    }

    public function test_missing_package_and_coach_have_clear_unavailable_states(): void
    {
        Livewire::actingAs(User::factory()->create(['role' => 'member']))
            ->test('pages::dashboard.member.jadwal-pt.index')->assertSee('Tidak ada membership PT');
        $this->page($this->membership(['pt_id' => null]))->assertSee('Coach belum ditentukan');
    }

    public function test_booking_window_includes_today_through_seven_days_ahead(): void
    {
        $membership = $this->membership();
        $page = $this->page($membership)->assertSee('Booking tersedia mulai hari ini sampai 7 hari ke depan');
        $calendar = $page->get('calendar');
        foreach ($calendar as $day) {
            foreach ($day['slots'] as $slot) {
                $this->assertNull($slot['reason']);
            }
        }

        foreach (['2026-09-29', '2026-09-30'] as $date) {
            $page->call('openBookingModal', $date, '12:00')->assertHasErrors('booking')->assertSet('showBookingModal', false);
            try {
                $this->submit($membership, '12:00', $date);
                $this->fail('Booking beyond seven days was accepted.');
            } catch (ValidationException $exception) {
                $this->assertSame('Booking hanya bisa dibuat mulai hari ini sampai 7 hari ke depan.', $exception->errors()['booking'][0]);
            }
        }
        $this->assertDatabaseCount('pt_bookings', 0);
        $this->assertModelExists($this->submit($membership, '22:00', '2026-09-28'));
    }

    #[DataProvider('dailyLimitStatuses')]
    public function test_daily_limit_applies_across_memberships_and_coaches(string $status): void
    {
        $membership = $this->membership();
        $otherPackage = $this->membership(['user_id' => $membership->user_id]);
        $this->booking($otherPackage, ['status' => $status]);
        $page = $this->page($membership);
        $this->assertNotNull($page->get('calendar')[1]['slots'][1]['reason']);
        $page->call('openBookingModal', '2026-09-22', '08:00')->assertHasErrors('booking');
        $this->assertModelExists($this->submit($membership, '08:00', '2026-09-23'));
        $this->expectException(ValidationException::class);
        $this->submit($membership, '08:00');
    }

    public static function dailyLimitStatuses(): array
    {
        return [['pending'], ['approved']];
    }

    public function test_daily_limit_is_rechecked_when_confirmation_is_stale(): void
    {
        $membership = $this->membership();
        $page = $this->page($membership)->call('openBookingModal', '2026-09-22', '08:00');
        $this->submit($membership, '07:00');
        $page->call('book')->assertHasErrors('booking');
        $this->assertDatabaseCount('pt_bookings', 1);
    }

    public function test_tomorrow_confirmation_still_works_after_midnight(): void
    {
        $this->travelTo(now()->setTime(23, 59));
        $page = $this->page($this->membership())->call('openBookingModal', '2026-09-22', '07:00');
        $this->travelTo(now()->addMinute());
        $page->call('book')->assertHasNoErrors()->assertSet('showBookingModal', false);
        $this->assertDatabaseCount('pt_bookings', 1);
    }

    public function test_today_booking_succeeds_before_start_and_is_rejected_at_start(): void
    {
        $membership = $this->membership();
        $page = $this->page($membership)->call('openBookingModal', '2026-09-21', '07:00');
        $page->call('book')->assertHasNoErrors();
        $this->assertDatabaseHas('pt_bookings', ['membership_id' => $membership->id, 'booking_date' => '2026-09-21', 'status' => 'pending']);

        $page->call('openBookingModal', '2026-09-21', '08:00');
        $this->travelTo(now()->setTime(8, 0));
        $page->call('book')->assertHasErrors('booking');
        $this->assertDatabaseCount('pt_bookings', 1);
        $this->expectException(ValidationException::class);
        $this->submit($membership, '08:00', '2026-09-21');
    }

    public function test_tomorrow_booking_works_across_a_month_boundary(): void
    {
        $this->travelTo(now()->setDate(2026, 9, 30)->setTime(23, 59));
        $membership = $this->membership();
        $this->assertModelExists($this->submit($membership, '07:00', '2026-10-01'));
    }

    public function test_pending_cancellation_immediately_releases_slot_and_quota(): void
    {
        $membership = $this->membership(['remaining_sessions' => 1]);
        $booking = $this->booking($membership, ['status' => 'pending']);
        $page = $this->page($membership)->call('openDetailModal', $booking->id)->assertSee('Batalkan Booking')
            ->call('openCancelModal', $booking->id)->assertSet('showCancelModal', true)
            ->set('cancelReason', 'Ada keperluan keluarga')->call('cancelBooking')->assertHasNoErrors()
            ->assertSet('showCancelModal', false);

        $booking->refresh();
        $this->assertSame('cancelled', $booking->status);
        $this->assertSame($membership->user_id, $booking->cancelled_by);
        $this->assertNotNull($booking->cancelled_at);
        $this->assertNull($booking->cancellation_requested_at);
        $this->assertSame(1, $membership->fresh()->remaining_sessions);
        $this->assertFalse($page->get('calendar')[1]['slots'][0]['occupied']);
        $this->assertModelExists($this->submit($membership));
    }

    public function test_approved_cancellation_requests_approval_and_keeps_the_slot_occupied(): void
    {
        $membership = $this->membership();
        $booking = $this->booking($membership);
        $page = $this->page($membership)->call('openDetailModal', $booking->id)->assertSee('Ajukan Pembatalan')
            ->call('openCancelModal', $booking->id)->set('cancelReason', 'Jadwal kerja berubah')
            ->call('cancelBooking')->assertHasNoErrors()->assertSee('Pending Cancel');

        $booking->refresh();
        $this->assertSame('approved', $booking->status);
        $this->assertNotNull($booking->cancellation_requested_at);
        $this->assertNull($booking->cancelled_at);
        $this->assertSame($membership->user_id, $booking->cancelled_by);
        $this->assertSame(10, $membership->fresh()->remaining_sessions);
        $this->assertTrue($page->get('calendar')[1]['slots'][0]['occupied']);
        $page->call('openDetailModal', $booking->id)->assertDontSee('Ajukan Pembatalan');
        $this->expectException(ValidationException::class);
        $this->submit($membership);
    }

    public function test_shared_package_member_can_request_cancellation(): void
    {
        $membership = $this->membership();
        $member = User::factory()->create(['role' => 'member']);
        $membership->members()->attach($member);
        $booking = $this->booking($membership);
        app(CancelMemberPtBooking::class)->execute($member, $booking->id, 'Tidak bisa datang');
        $this->assertSame($member->id, $booking->fresh()->cancelled_by);
        $this->assertTrue($booking->fresh()->isCancellationPending());
    }

    public function test_foreign_booking_cannot_be_cancelled_or_opened(): void
    {
        $membership = $this->membership();
        $booking = $this->booking($this->membership());
        $this->page($membership)->call('openCancelModal', $booking->id)->assertNotFound();
        $this->expectException(HttpException::class);
        app(CancelMemberPtBooking::class)->execute($membership->user, $booking->id, 'Tidak bisa datang');
    }

    #[DataProvider('nonCancellableBookings')]
    public function test_started_attended_or_rejected_bookings_cannot_be_cancelled(array $attributes): void
    {
        $membership = $this->membership();
        $booking = $this->booking($membership, $attributes);
        $this->page($membership)->call('openCancelModal', $booking->id)->assertHasErrors('cancelReason');
        $this->expectException(ValidationException::class);
        app(CancelMemberPtBooking::class)->execute($membership->user, $booking->id, 'Tidak bisa datang');
    }

    public static function nonCancellableBookings(): array
    {
        return [[['attendance' => 'attended']], [['attendance' => 'noshow']], [['status' => 'rejected']], [['booking_date' => '2026-09-21', 'booking_time' => '06:00:00']]];
    }

    public function test_cancellation_reason_is_required_and_repeated_request_preserves_original_record(): void
    {
        $membership = $this->membership();
        $booking = $this->booking($membership);
        $page = $this->page($membership)->call('openCancelModal', $booking->id)
            ->set('cancelReason', '  ')->call('cancelBooking')->assertHasErrors('cancelReason');
        $page->set('cancelReason', 'Tidak bisa datang')->call('cancelBooking')->assertHasNoErrors();
        $firstRequestedAt = $booking->fresh()->cancellation_requested_at;
        $this->travel(5)->minutes();
        app(CancelMemberPtBooking::class)->execute($membership->user, $booking->id, 'Alasan lain untuk duplikat');
        $this->assertSame('Tidak bisa datang', $booking->fresh()->cancellation_reason);
        $this->assertTrue($firstRequestedAt->equalTo($booking->fresh()->cancellation_requested_at));
    }

    #[DataProvider('cancellationCutoffs')]
    public function test_cancellation_enforces_three_hour_cutoff(string $status, string $time, bool $allowed): void
    {
        $membership = $this->membership();
        $booking = $this->booking($membership, ['status' => $status]);
        $this->travelTo(now()->setDate(2026, 9, 22)->setTimeFromTimeString($time));
        $page = $this->page($membership)->call('openDetailModal', $booking->id);

        if ($allowed) {
            $page->call('openCancelModal', $booking->id)->set('cancelReason', 'Tidak bisa datang')
                ->call('cancelBooking')->assertHasNoErrors();
            $this->assertSame($status === 'pending' ? 'cancelled' : 'approved', $booking->fresh()->status);
            $this->assertSame($status === 'approved', $booking->fresh()->isCancellationPending());
        } else {
            $page->assertSee('Pembatalan tidak tersedia mulai 3 jam sebelum jadwal sesi.')
                ->call('openCancelModal', $booking->id)->assertHasErrors('cancelReason');
            $this->expectException(ValidationException::class);
            app(CancelMemberPtBooking::class)->execute($membership->user, $booking->id, 'Tidak bisa datang');
        }
    }

    public static function cancellationCutoffs(): array
    {
        return [
            ['pending', '03:59:59', true], ['approved', '03:59:59', true],
            ['pending', '04:00:00', false], ['approved', '04:00:00', false],
            ['pending', '04:00:01', false], ['approved', '04:00:01', false],
        ];
    }

    public function test_cancellation_rechecks_cutoff_after_modal_was_opened(): void
    {
        $membership = $this->membership();
        $booking = $this->booking($membership, ['status' => 'pending']);
        $this->travelTo(now()->setDate(2026, 9, 22)->setTime(3, 59, 59));
        $page = $this->page($membership)->call('openCancelModal', $booking->id);
        $this->travel(1)->seconds();
        $page->set('cancelReason', 'Tidak bisa datang')->call('cancelBooking')->assertHasErrors('cancelReason');
        $this->assertSame('pending', $booking->fresh()->status);
        $this->assertNull($booking->fresh()->cancelled_at);
    }

    public function test_cancellation_rechecks_attendance_after_modal_was_opened(): void
    {
        $membership = $this->membership();
        $booking = $this->booking($membership);
        $page = $this->page($membership)->call('openCancelModal', $booking->id);
        $booking->update(['attendance' => 'attended']);
        $page->set('cancelReason', 'Tidak bisa datang')->call('cancelBooking')->assertHasErrors('cancelReason');
        $this->assertNull($booking->fresh()->cancellation_requested_at);
    }

    public function test_booking_approved_while_cancel_modal_is_open_requires_approval(): void
    {
        $membership = $this->membership();
        $booking = $this->booking($membership, ['status' => 'pending']);
        $page = $this->page($membership)->call('openCancelModal', $booking->id);
        $booking->update(['status' => 'approved']);

        $page->set('cancelReason', 'Tidak bisa datang')->call('cancelBooking')->assertHasNoErrors();

        $this->assertSame('approved', $booking->fresh()->status);
        $this->assertTrue($booking->fresh()->isCancellationPending());
        $this->assertNull($booking->fresh()->cancelled_at);
    }

    private function page(Membership $membership, bool $weekly = true): Testable
    {
        $page = Livewire::actingAs($membership->user)->test('pages::dashboard.member.jadwal-pt.index');

        return $weekly ? $page->call('setDayView', 'all') : $page;
    }

    private function submit(Membership $membership, string $time = '07:00', string $date = '2026-09-22'): PtBooking
    {
        return app(CreateMemberPtBooking::class)->execute($membership->user, $membership->id, $date, $time);
    }

    /** @param array<string, mixed> $attributes */
    private function membership(array $attributes = []): Membership
    {
        return Membership::create([
            'user_id' => User::factory()->create(['role' => 'member'])->id,
            'pt_id' => User::factory()->create(['role' => 'pt'])->id,
            'type' => 'pt', 'base_price' => 300000, 'price_paid' => 300000,
            'total_paid' => 300000, 'payment_status' => 'paid',
            'start_date' => '2026-09-01', 'pt_end_date' => '2026-10-21',
            'status' => 'active', 'is_active' => true, 'total_sessions' => 10, 'remaining_sessions' => 10,
            ...$attributes,
        ]);
    }

    /** @param array<string, mixed> $attributes */
    private function booking(Membership $membership, array $attributes = []): PtBooking
    {
        return PtBooking::create([
            'membership_id' => $membership->id, 'member_id' => $membership->user_id,
            'pt_id' => $membership->pt_id, 'booking_date' => '2026-09-22', 'booking_time' => '07:00:00',
            'status' => 'approved', 'type' => 'fleksibel', 'attendance' => 'not_yet', 'is_free' => false,
            ...$attributes,
        ]);
    }
}
