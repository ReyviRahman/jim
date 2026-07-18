<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\DeviceEvent;
use App\Models\Membership;
use App\Models\PtBooking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DeviceEventWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_stores_hikvision_xml_event(): void
    {
        $xml = <<<'XML'
<EventNotificationAlert>
    <eventType>AccessControllerEvent</eventType>
    <eventState>active</eventState>
    <eventDescription>Access Control Event</eventDescription>
    <dateTime>2025-10-30T14:30:00Z</dateTime>
    <ActivePost>
        <eventType>AccessControllerEvent</eventType>
        <employeeNoString>EMP001</employeeNoString>
        <name>John Doe</name>
        <cardNo>1234567890</cardNo>
        <doorNo>1</doorNo>
        <swipeResult>success</swipeResult>
        <attendanceStatus>checkIn</attendanceStatus>
        <currentVerifyMode>cardOrFaceOrFp</currentVerifyMode>
    </ActivePost>
</EventNotificationAlert>
XML;

        $response = $this->call('POST', '/api/absensi', [], [], [], [
            'CONTENT_TYPE' => 'application/xml',
        ], $xml);

        $response->assertStatus(200);
        $response->assertSee('OK');

        $this->assertDatabaseHas('device_events', [
            'device_code' => 'HQ-BIO-01',
            'employee_no' => 'EMP001',
            'name' => 'John Doe',
            'card_no' => null,
            'door_no' => null,
            'swipe_result' => null,
            'attendance_status' => 'checkIn',
            'verify_mode' => 'cardOrFaceOrFp',
            'is_found' => false,
            'status' => 'received',
            'payload' => '',
        ]);
    }

    public function test_it_marks_an_event_as_found_when_its_employee_number_matches_a_user_id(): void
    {
        $user = $this->createUser(['role' => 'member']);

        $response = $this->postJson('/api/absensi', $this->attendancePayload(
            $user,
            'checkIn',
            '2025-10-30T14:32:00+07:00',
        ));

        $response->assertOk();

        $deviceEvent = DeviceEvent::query()->where('employee_no', (string) $user->id)->firstOrFail();
        $attendance = Attendance::query()->whereBelongsTo($user)->firstOrFail();

        $this->assertTrue($deviceEvent->is_found);
        $this->assertSame($deviceEvent->id, $attendance->device_event_id);
        $this->assertSame('checkIn', $attendance->attendance_status);
        $this->assertSame('2025-10-30 14:32:00', $attendance->check_in_time->format('Y-m-d H:i:s'));
        $this->assertNull($attendance->membership_id);
        $this->assertNull($attendance->type);

        $this->assertDatabaseHas('attendances', [
            'device_event_id' => $deviceEvent->id,
            'user_id' => $user->id,
            'membership_id' => null,
            'type' => null,
            'attendance_status' => 'checkIn',
            'check_in_time' => '2025-10-30 14:32:00',
        ]);
    }

    public function test_it_stores_attendance_for_every_user_role_including_inactive_users(): void
    {
        $roles = ['admin', 'pt', 'member', 'kasir_gym', 'sales', 'kasir_minum', 'head_coach'];

        foreach ($roles as $index => $role) {
            $user = $this->createUser(['role' => $role]);

            $this->postJson('/api/absensi', $this->attendancePayload(
                $user,
                'checkIn',
                sprintf('2025-11-01T09:%02d:00+07:00', $index),
            ))->assertOk();

            $this->assertDatabaseHas('attendances', [
                'user_id' => $user->id,
                'membership_id' => null,
                'type' => null,
                'attendance_status' => 'checkIn',
            ]);
        }

        $inactiveUser = $this->createUser(['is_active' => false]);

        $this->postJson('/api/absensi', $this->attendancePayload(
            $inactiveUser,
            'checkIn',
            '2025-11-01T10:00:00+07:00',
        ))->assertOk();

        $this->assertDatabaseHas('attendances', [
            'user_id' => $inactiveUser->id,
            'attendance_status' => 'checkIn',
        ]);
        $this->assertDatabaseCount('attendances', 8);
    }

    public function test_check_in_and_check_out_are_stored_as_separate_attendance_rows(): void
    {
        $user = $this->createUser();

        $this->postJson('/api/absensi', $this->attendancePayload(
            $user,
            'checkIn',
            '2025-11-02T08:00:00+07:00',
        ))->assertOk();
        $this->postJson('/api/absensi', $this->attendancePayload(
            $user,
            'checkOut',
            '2025-11-02T17:00:00+07:00',
        ))->assertOk();

        $this->assertDatabaseCount('device_events', 2);
        $this->assertDatabaseCount('attendances', 2);
        $this->assertDatabaseHas('attendances', [
            'user_id' => $user->id,
            'attendance_status' => 'checkIn',
            'check_in_time' => '2025-11-02 08:00:00',
        ]);
        $this->assertDatabaseHas('attendances', [
            'user_id' => $user->id,
            'attendance_status' => 'checkOut',
            'check_in_time' => '2025-11-02 17:00:00',
        ]);
    }

    public function test_unknown_employee_only_creates_a_device_event(): void
    {
        $this->postJson('/api/absensi', [
            'eventType' => 'AccessControllerEvent',
            'dateTime' => '2025-11-03T08:00:00+07:00',
            'AccessControllerEvent' => [
                'employeeNoString' => '999999',
                'name' => 'Unknown Employee',
                'attendanceStatus' => 'checkIn',
                'currentVerifyMode' => 'cardOrFaceOrFp',
            ],
        ])->assertOk();

        $this->assertDatabaseHas('device_events', [
            'employee_no' => '999999',
            'is_found' => false,
        ]);
        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_retry_creates_attendance_when_an_unknown_employee_is_added_to_users(): void
    {
        $payload = [
            'eventType' => 'AccessControllerEvent',
            'dateTime' => '2025-11-03T09:00:00+07:00',
            'AccessControllerEvent' => [
                'employeeNoString' => '900001',
                'name' => 'New Employee',
                'attendanceStatus' => 'checkIn',
                'currentVerifyMode' => 'cardOrFaceOrFp',
            ],
        ];

        $this->postJson('/api/absensi', $payload)->assertOk();
        $this->assertDatabaseCount('attendances', 0);

        $user = $this->createUser(['id' => 900001]);

        $this->postJson('/api/absensi', $payload)->assertOk();

        $this->assertDatabaseCount('device_events', 1);
        $this->assertDatabaseCount('attendances', 1);
        $this->assertDatabaseHas('device_events', [
            'employee_no' => (string) $user->id,
            'is_found' => true,
        ]);
        $this->assertDatabaseHas('attendances', [
            'user_id' => $user->id,
            'attendance_status' => 'checkIn',
        ]);
    }

    public function test_it_stores_json_event(): void
    {
        $payload = [
            'eventType' => 'AccessControllerEvent',
            'eventState' => 'active',
            'dateTime' => '2025-10-30T14:35:00Z',
            'AccessControllerEvent' => [
                'employeeNoString' => 'EMP002',
                'name' => 'Jane Doe',
                'cardNo' => '0987654321',
                'doorNo' => '2',
                'attendanceStatus' => 'checkIn',
                'currentVerifyMode' => 'cardOrFaceOrFp',
            ],
        ];

        $response = $this->postJson('/api/absensi', $payload);

        $response->assertStatus(200);

        $this->assertDatabaseHas('device_events', [
            'device_code' => 'HQ-BIO-01',
            'employee_no' => 'EMP002',
            'name' => 'Jane Doe',
            'attendance_status' => 'checkIn',
            'verify_mode' => 'cardOrFaceOrFp',
            'status' => 'received',
            'payload' => '',
        ]);
    }

    public function test_it_stores_multipart_event_log_payload(): void
    {
        $eventLog = json_encode([
            'eventType' => 'AccessControllerEvent',
            'dateTime' => '2025-10-30T14:40:00+07:00',
            'AccessControllerEvent' => [
                'name' => 'Reyvi Rahman',
                'employeeNoString' => '126352131231',
                'doorNo' => 1,
                'attendanceStatus' => 'checkOut',
                'currentVerifyMode' => 'cardOrFaceOrFp',
            ],
        ]);

        $response = $this->call('POST', '/api/absensi', [
            'event_log' => $eventLog,
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('device_events', [
            'device_code' => 'HQ-BIO-01',
            'name' => 'Reyvi Rahman',
            'employee_no' => '126352131231',
            'attendance_status' => 'checkOut',
            'verify_mode' => 'cardOrFaceOrFp',
            'status' => 'received',
            'payload' => '',
        ]);
    }

    public function test_it_ignores_empty_heartbeat_payload(): void
    {
        $response = $this->call('POST', '/api/absensi', [], [], [], [], '   ');

        $response->assertStatus(200);
        $response->assertSee('OK');

        $this->assertDatabaseMissing('device_events', [
            'device_code' => 'HQ-BIO-01',
        ]);
    }

    public function test_it_ignores_noise_events_without_employee_data(): void
    {
        $eventLog = json_encode([
            'eventType' => 'AccessControllerEvent',
            'dateTime' => '2025-10-30T14:45:00+07:00',
            'AccessControllerEvent' => [
                'doorNo' => 1,
                'attendanceStatus' => 'undefined',
                'currentVerifyMode' => 'invalid',
            ],
        ]);

        $response = $this->call('POST', '/api/absensi', [
            'event_log' => $eventLog,
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseMissing('device_events', [
            'device_code' => 'HQ-BIO-01',
            'event_type' => 'AccessControllerEvent',
            'verify_mode' => 'invalid',
        ]);
    }

    public function test_it_returns_ok_without_storing_invalid_xml(): void
    {
        $response = $this->call('POST', '/api/absensi', [], [], [], [
            'CONTENT_TYPE' => 'application/xml',
        ], '<not valid xml');

        $response->assertStatus(200);

        $this->assertDatabaseCount('device_events', 0);
    }

    public function test_it_ignores_attendance_event_without_employee_number(): void
    {
        $response = $this->postJson('/api/absensi', [
            'eventType' => 'AccessControllerEvent',
            'dateTime' => '2025-10-30T14:50:00+07:00',
            'AccessControllerEvent' => [
                'name' => 'Unknown Employee',
                'attendanceStatus' => 'checkIn',
                'currentVerifyMode' => 'cardOrFaceOrFp',
            ],
        ]);

        $response->assertOk();

        $this->assertDatabaseCount('device_events', 0);
    }

    public function test_it_stores_identical_device_retries_only_once(): void
    {
        $user = $this->createUser();
        $payload = $this->attendancePayload($user, 'checkOut', '2025-10-30T14:55:00+07:00');

        $this->postJson('/api/absensi', $payload)->assertOk();
        $this->postJson('/api/absensi', $payload)->assertOk();

        $this->assertDatabaseCount('device_events', 1);
        $this->assertDatabaseCount('attendances', 1);
        $this->assertDatabaseHas('device_events', [
            'employee_no' => (string) $user->id,
            'name' => $user->name,
            'is_found' => true,
            'attendance_status' => 'checkOut',
        ]);
    }

    public function test_legacy_attendance_types_remain_supported(): void
    {
        foreach (['gym', 'pt', 'visit', 'coach_attendance'] as $index => $type) {
            $user = $this->createUser();

            Attendance::create([
                'user_id' => $user->id,
                'membership_id' => null,
                'type' => $type,
                'attendance_status' => null,
                'check_in_time' => sprintf('2025-11-04 08:%02d:00', $index),
            ]);

            $this->assertDatabaseHas('attendances', [
                'user_id' => $user->id,
                'type' => $type,
                'attendance_status' => null,
            ]);
        }
    }

    public function test_new_device_attendance_marks_todays_pending_booking_attended_using_server_date(): void
    {
        $member = $this->createUser();
        $membership = $this->createPtMembership($member, ['remaining_sessions' => 2]);
        $booking = $this->createPtBooking($member, $membership, ['status' => 'pending']);

        $this->postJson('/api/absensi', $this->attendancePayload(
            $member,
            'checkIn',
            '2025-01-01T08:00:00+07:00',
        ))->assertOk();

        $this->assertSame('attended', $booking->fresh()->attendance);
        $this->assertSame('approved', $booking->fresh()->status);
        $this->assertSame(1, $membership->fresh()->remaining_sessions);
    }

    public function test_check_out_marks_an_approved_booking_attended_and_completes_its_last_session(): void
    {
        $member = $this->createUser();
        $membership = $this->createPtMembership($member, ['remaining_sessions' => 1]);
        $booking = $this->createPtBooking($member, $membership);

        $this->postJson('/api/absensi', $this->attendancePayload(
            $member,
            'checkOut',
            '2025-01-02T17:00:00+07:00',
        ))->assertOk();

        $this->assertSame('attended', $booking->fresh()->attendance);
        $this->assertSame('approved', $booking->fresh()->status);
        $this->assertSame(0, $membership->fresh()->remaining_sessions);
        $this->assertSame('completed', $membership->fresh()->status);
    }

    public function test_it_selects_the_nearest_booking_using_server_time_and_stops_after_all_are_attended(): void
    {
        $this->travelTo(Carbon::parse('2026-07-19 07:00:00', config('app.timezone')));

        $member = $this->createUser();
        $membership = $this->createPtMembership($member, ['remaining_sessions' => 3]);
        $morningBooking = $this->createPtBooking($member, $membership, ['booking_time' => '08:00:00']);
        $afternoonBooking = $this->createPtBooking($member, $membership, ['booking_time' => '14:00:00']);

        $this->postJson('/api/absensi', $this->attendancePayload(
            $member,
            'checkIn',
            '2030-01-03T23:00:00+07:00',
        ))->assertOk();

        $this->assertSame('attended', $morningBooking->fresh()->attendance);
        $this->assertSame('not_yet', $afternoonBooking->fresh()->attendance);
        $this->assertSame(2, $membership->fresh()->remaining_sessions);

        $this->travelTo(Carbon::parse('2026-07-19 15:00:00', config('app.timezone')));

        $this->postJson('/api/absensi', $this->attendancePayload(
            $member,
            'checkOut',
            '2030-01-03T23:01:00+07:00',
        ))->assertOk();

        $this->assertSame('attended', $afternoonBooking->fresh()->attendance);
        $this->assertSame(1, $membership->fresh()->remaining_sessions);

        $this->travelTo(Carbon::parse('2026-07-19 16:00:00', config('app.timezone')));

        $this->postJson('/api/absensi', $this->attendancePayload(
            $member,
            'checkIn',
            '2030-01-03T23:02:00+07:00',
        ))->assertOk();

        $this->assertSame(1, $membership->fresh()->remaining_sessions);
        $this->assertDatabaseCount('attendances', 3);
    }

    public function test_equal_booking_distance_selects_the_earlier_time_then_the_lowest_id(): void
    {
        $this->travelTo(Carbon::parse('2026-07-19 11:00:00', config('app.timezone')));

        $member = $this->createUser();
        $membership = $this->createPtMembership($member, ['remaining_sessions' => 5]);
        $laterBooking = $this->createPtBooking($member, $membership, ['booking_time' => '14:00:00']);
        $earlierBooking = $this->createPtBooking($member, $membership, ['booking_time' => '08:00:00']);
        $sameTimeHigherIdBooking = $this->createPtBooking($member, $membership, ['booking_time' => '08:00:00']);

        $this->postJson('/api/absensi', $this->attendancePayload(
            $member,
            'checkIn',
            '2030-01-04T23:00:00+07:00',
        ))->assertOk();

        $this->assertSame('not_yet', $laterBooking->fresh()->attendance);
        $this->assertSame('attended', $earlierBooking->fresh()->attendance);
        $this->assertSame('not_yet', $sameTimeHigherIdBooking->fresh()->attendance);
        $this->assertSame(4, $membership->fresh()->remaining_sessions);
    }

    public function test_shared_membership_user_can_attend_the_memberships_booking(): void
    {
        $this->travelTo(Carbon::parse('2026-07-19 10:00:00', config('app.timezone')));

        $membershipOwner = $this->createUser();
        $sharedMember = $this->createUser();
        $membership = $this->createPtMembership($membershipOwner, ['remaining_sessions' => 2]);
        $membership->members()->attach($sharedMember);
        $booking = $this->createPtBooking($membershipOwner, $membership, ['booking_time' => '10:00:00']);

        $this->postJson('/api/absensi', $this->attendancePayload(
            $sharedMember,
            'checkIn',
            '2030-01-05T23:00:00+07:00',
        ))->assertOk();

        $this->assertSame($membershipOwner->id, $booking->member_id);
        $this->assertSame('attended', $booking->fresh()->attendance);
        $this->assertSame(1, $membership->fresh()->remaining_sessions);
        $this->assertDatabaseHas('attendances', ['user_id' => $sharedMember->id]);
    }

    public function test_user_cannot_attend_a_booking_without_membership_users_access(): void
    {
        $this->travelTo(Carbon::parse('2026-07-19 10:00:00', config('app.timezone')));

        $membershipOwner = $this->createUser();
        $unrelatedUser = $this->createUser();
        $membership = $this->createPtMembership($membershipOwner, ['remaining_sessions' => 2]);
        $booking = $this->createPtBooking($membershipOwner, $membership, ['booking_time' => '10:00:00']);

        $this->postJson('/api/absensi', $this->attendancePayload(
            $unrelatedUser,
            'checkIn',
            '2030-01-06T23:00:00+07:00',
        ))->assertOk();

        $this->assertSame('not_yet', $booking->fresh()->attendance);
        $this->assertSame(2, $membership->fresh()->remaining_sessions);
        $this->assertDatabaseHas('attendances', ['user_id' => $unrelatedUser->id]);
    }

    public function test_ineligible_bookings_are_not_changed_but_attendance_is_still_created(): void
    {
        $member = $this->createUser();
        $membership = $this->createPtMembership($member);
        $bookings = collect([
            $this->createPtBooking($member, $membership, ['booking_date' => today()->subDay()]),
            $this->createPtBooking($member, $membership, ['status' => 'cancelled']),
            $this->createPtBooking($member, $membership, ['status' => 'rejected']),
            $this->createPtBooking($member, $membership, ['attendance' => 'noshow']),
            $this->createPtBooking($member, $membership, ['attendance' => 'attended']),
        ]);

        $this->postJson('/api/absensi', $this->attendancePayload(
            $member,
            'checkIn',
            '2025-01-04T09:00:00+07:00',
        ))->assertOk();

        $bookings->each(function (PtBooking $booking): void {
            $this->assertSame($booking->attendance, $booking->fresh()->attendance);
            $this->assertSame($booking->status, $booking->fresh()->status);
        });
        $this->assertSame(10, $membership->fresh()->remaining_sessions);
        $this->assertDatabaseHas('attendances', ['user_id' => $member->id]);
    }

    public function test_free_booking_is_attended_without_reducing_sessions(): void
    {
        $member = $this->createUser();
        $membership = $this->createPtMembership($member, ['remaining_sessions' => 3]);
        $booking = $this->createPtBooking($member, $membership, ['is_free' => true]);

        $this->postJson('/api/absensi', $this->attendancePayload(
            $member,
            'checkIn',
            '2025-01-05T09:00:00+07:00',
        ))->assertOk();

        $this->assertSame('attended', $booking->fresh()->attendance);
        $this->assertSame(3, $membership->fresh()->remaining_sessions);
    }

    public function test_identical_retry_does_not_process_the_next_booking_or_reduce_sessions_twice(): void
    {
        $this->travelTo(Carbon::parse('2026-07-19 08:00:00', config('app.timezone')));

        $member = $this->createUser();
        $membership = $this->createPtMembership($member, ['remaining_sessions' => 3]);
        $firstBooking = $this->createPtBooking($member, $membership, ['booking_time' => '08:00:00']);
        $secondBooking = $this->createPtBooking($member, $membership, ['booking_time' => '09:00:00']);
        $payload = $this->attendancePayload($member, 'checkIn', '2025-01-06T09:00:00+07:00');

        $this->postJson('/api/absensi', $payload)->assertOk();
        $this->postJson('/api/absensi', $payload)->assertOk();

        $this->assertSame('attended', $firstBooking->fresh()->attendance);
        $this->assertSame('not_yet', $secondBooking->fresh()->attendance);
        $this->assertSame(2, $membership->fresh()->remaining_sessions);
    }

    public function test_distinct_check_in_and_check_out_events_can_process_two_bookings(): void
    {
        $this->travelTo(Carbon::parse('2026-07-19 08:00:00', config('app.timezone')));

        $member = $this->createUser();
        $membership = $this->createPtMembership($member, ['remaining_sessions' => 3]);
        $firstBooking = $this->createPtBooking($member, $membership, ['booking_time' => '08:00:00']);
        $secondBooking = $this->createPtBooking($member, $membership, ['booking_time' => '09:00:00']);

        $this->postJson('/api/absensi', $this->attendancePayload(
            $member,
            'checkIn',
            '2025-01-07T08:00:00+07:00',
        ))->assertOk();
        $this->postJson('/api/absensi', $this->attendancePayload(
            $member,
            'checkOut',
            '2025-01-07T09:00:00+07:00',
        ))->assertOk();

        $this->assertSame('attended', $firstBooking->fresh()->attendance);
        $this->assertSame('attended', $secondBooking->fresh()->attendance);
        $this->assertSame(1, $membership->fresh()->remaining_sessions);
    }

    public function test_zero_remaining_sessions_never_becomes_negative(): void
    {
        $member = $this->createUser();
        $membership = $this->createPtMembership($member, ['remaining_sessions' => 0]);
        $booking = $this->createPtBooking($member, $membership);

        $this->postJson('/api/absensi', $this->attendancePayload(
            $member,
            'checkIn',
            '2025-01-08T09:00:00+07:00',
        ))->assertOk();

        $this->assertSame('attended', $booking->fresh()->attendance);
        $this->assertSame(0, $membership->fresh()->remaining_sessions);
    }

    /**
     * @return array<string, mixed>
     */
    private function attendancePayload(User $user, string $status, string $dateTime): array
    {
        return [
            'eventType' => 'AccessControllerEvent',
            'dateTime' => $dateTime,
            'AccessControllerEvent' => [
                'employeeNoString' => (string) $user->id,
                'name' => $user->name,
                'attendanceStatus' => $status,
                'currentVerifyMode' => 'cardOrFaceOrFp',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createPtMembership(User $member, array $attributes = []): Membership
    {
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
            ...$attributes,
        ]);

        $membership->members()->attach($member);

        return $membership;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createPtBooking(User $member, Membership $membership, array $attributes = []): PtBooking
    {
        return PtBooking::create([
            'membership_id' => $membership->id,
            'member_id' => $member->id,
            'pt_id' => $membership->pt_id,
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
