<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\DeviceEvent;
use App\Models\Membership;
use App\Models\PtBooking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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
            'card_no' => '1234567890',
            'door_no' => '1',
            'swipe_result' => 'success',
            'attendance_status' => 'checkIn',
            'verify_mode' => 'cardOrFaceOrFp',
            'is_found' => false,
            'status' => 'received',
            'payload' => '',
        ]);
    }

    public function test_it_marks_an_event_as_found_when_its_employee_number_matches_a_user_id(): void
    {
        $this->travelTo(Carbon::parse('2026-08-29 08:15:00', config('app.timezone')));

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
        $this->assertNull($attendance->attendance_status);
        $this->assertSame('2026-08-29', $attendance->attendance_date->format('Y-m-d'));
        $this->assertSame('2026-08-29 08:15:00', $attendance->check_in_time->format('Y-m-d H:i:s'));
        $this->assertNull($attendance->check_out_time);
        $this->assertNull($attendance->membership_id);
        $this->assertNull($attendance->type);

        $this->assertDatabaseHas('attendances', [
            'device_event_id' => $deviceEvent->id,
            'user_id' => $user->id,
            'membership_id' => null,
            'type' => null,
            'attendance_status' => null,
            'attendance_date' => '2026-08-29',
            'check_in_time' => '2026-08-29 08:15:00',
            'check_out_time' => null,
        ]);
    }

    public function test_it_stores_attendance_for_every_user_role_including_inactive_users(): void
    {
        $this->travelTo(Carbon::parse('2026-08-30 09:00:00', config('app.timezone')));

        $roles = ['admin', 'pt', 'member', 'kasir_gym', 'sales', 'kasir_minum', 'head_coach'];

        foreach ($roles as $index => $role) {
            $user = $this->createUser(['role' => $role]);

            $this->postJson('/api/absensi', $this->attendancePayload(
                $user,
                $index % 2 === 0 ? 'checkIn' : 'checkOut',
                sprintf('2025-11-01T09:%02d:00+07:00', $index),
            ))->assertOk();

            $this->assertDatabaseHas('attendances', [
                'user_id' => $user->id,
                'membership_id' => null,
                'type' => null,
                'attendance_status' => null,
                'attendance_date' => '2026-08-30',
                'check_in_time' => '2026-08-30 09:00:00',
                'check_out_time' => null,
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
            'attendance_status' => null,
            'attendance_date' => '2026-08-30',
            'check_in_time' => '2026-08-30 09:00:00',
            'check_out_time' => null,
        ]);
        $this->assertDatabaseCount('attendances', 8);
    }

    public function test_first_and_subsequent_events_use_server_times_regardless_of_status(): void
    {
        $this->travelTo(Carbon::parse('2026-08-31 08:00:00', config('app.timezone')));

        $user = $this->createUser(['role' => 'pt']);

        $this->postJson('/api/absensi', $this->attendancePayload(
            $user,
            'checkOut',
            '2030-11-02T20:00:00+07:00',
        ))->assertOk();

        $this->travelTo(Carbon::parse('2026-08-31 17:00:00', config('app.timezone')));

        $this->postJson('/api/absensi', $this->attendancePayload(
            $user,
            'checkIn',
            '2020-11-02T05:00:00+07:00',
        ))->assertOk();

        $creatorEvent = DeviceEvent::query()
            ->where('employee_no', (string) $user->id)
            ->oldest('id')
            ->firstOrFail();
        $attendance = Attendance::query()->whereBelongsTo($user)->firstOrFail();

        $this->assertDatabaseCount('device_events', 2);
        $this->assertDatabaseCount('attendances', 1);
        $this->assertSame($creatorEvent->id, $attendance->device_event_id);
        $this->assertNull($attendance->attendance_status);
        $this->assertSame('2026-08-31', $attendance->attendance_date->format('Y-m-d'));
        $this->assertSame('2026-08-31 08:00:00', $attendance->check_in_time->format('Y-m-d H:i:s'));
        $this->assertSame('2026-08-31 17:00:00', $attendance->check_out_time->format('Y-m-d H:i:s'));
    }

    public function test_first_check_in_is_immutable_and_each_distinct_event_updates_check_out(): void
    {
        $this->travelTo(Carbon::parse('2026-09-01 09:00:00', config('app.timezone')));

        $user = $this->createUser();

        $this->postJson('/api/absensi', $this->attendancePayload(
            $user,
            'checkIn',
            '2025-11-03T09:00:00+07:00',
        ))->assertOk();

        $this->travelTo(Carbon::parse('2026-09-01 10:00:00', config('app.timezone')));

        $this->postJson('/api/absensi', $this->attendancePayload(
            $user,
            'checkIn',
            '2030-11-03T08:00:00+07:00',
        ))->assertOk();

        $this->travelTo(Carbon::parse('2026-09-01 11:00:00', config('app.timezone')));

        $this->postJson('/api/absensi', $this->attendancePayload(
            $user,
            'checkOut',
            '2020-11-03T10:00:00+07:00',
        ))->assertOk();

        $this->assertDatabaseCount('device_events', 3);
        $this->assertDatabaseCount('attendances', 1);

        $attendance = Attendance::query()->whereBelongsTo($user)->firstOrFail();

        $this->assertSame('2026-09-01 09:00:00', $attendance->check_in_time->format('Y-m-d H:i:s'));
        $this->assertSame('2026-09-01 11:00:00', $attendance->check_out_time->format('Y-m-d H:i:s'));
    }

    public function test_member_and_employee_roles_both_receive_check_out_on_the_second_event(): void
    {
        $this->travelTo(Carbon::parse('2026-09-02 07:30:00', config('app.timezone')));

        foreach (['member', 'head_coach'] as $role) {
            $user = $this->createUser(['role' => $role]);
            $this->postJson('/api/absensi', $this->attendancePayload(
                $user,
                'checkOut',
                '2030-11-04T17:00:00+07:00',
            ))->assertOk();

            $this->travelTo(Carbon::parse('2026-09-02 18:15:00', config('app.timezone')));

            $this->postJson('/api/absensi', $this->attendancePayload(
                $user,
                'checkIn',
                '2020-11-04T08:00:00+07:00',
            ))->assertOk();

            $attendance = Attendance::query()->whereBelongsTo($user)->firstOrFail();

            $this->assertSame('2026-09-02 07:30:00', $attendance->check_in_time->format('Y-m-d H:i:s'));
            $this->assertSame('2026-09-02 18:15:00', $attendance->check_out_time->format('Y-m-d H:i:s'));

            $this->travelTo(Carbon::parse('2026-09-02 07:30:00', config('app.timezone')));
        }

        $this->assertDatabaseCount('device_events', 4);
        $this->assertDatabaseCount('attendances', 2);
    }

    public function test_check_out_first_then_check_in_uses_the_same_row_and_keeps_the_creator_event(): void
    {
        $this->travelTo(Carbon::parse('2026-09-03 17:00:00', config('app.timezone')));

        $user = $this->createUser(['role' => 'kasir_gym']);

        $this->postJson('/api/absensi', $this->attendancePayload(
            $user,
            'checkOut',
            '2025-11-05T17:00:00+07:00',
        ))->assertOk();

        $creatorEvent = DeviceEvent::query()->firstOrFail();

        $this->travelTo(Carbon::parse('2026-09-03 18:00:00', config('app.timezone')));

        $this->postJson('/api/absensi', $this->attendancePayload(
            $user,
            'checkIn',
            '2025-11-05T08:00:00+07:00',
        ))->assertOk();

        $attendance = Attendance::query()->whereBelongsTo($user)->firstOrFail();

        $this->assertDatabaseCount('device_events', 2);
        $this->assertDatabaseCount('attendances', 1);
        $this->assertSame($creatorEvent->id, $attendance->device_event_id);
        $this->assertSame('2026-09-03', $attendance->attendance_date->format('Y-m-d'));
        $this->assertSame('2026-09-03 17:00:00', $attendance->check_in_time->format('Y-m-d H:i:s'));
        $this->assertSame('2026-09-03 18:00:00', $attendance->check_out_time->format('Y-m-d H:i:s'));
    }

    public function test_daily_attendance_uses_the_server_date_in_asia_jakarta(): void
    {
        $this->travelTo(Carbon::parse('2026-09-04 23:59:00', config('app.timezone')));

        $firstUser = $this->createUser();
        $secondUser = $this->createUser();

        $this->postJson('/api/absensi', $this->attendancePayload(
            $firstUser,
            'checkIn',
            '2030-11-05T17:30:00Z',
        ))->assertOk();

        $this->travelTo(Carbon::parse('2026-09-05 00:01:00', config('app.timezone')));

        $this->postJson('/api/absensi', $this->attendancePayload(
            $firstUser,
            'checkIn',
            '2020-11-05T16:30:00Z',
        ))->assertOk();
        $this->postJson('/api/absensi', $this->attendancePayload(
            $secondUser,
            'checkIn',
            '2025-11-05T17:30:00Z',
        ))->assertOk();

        $firstUserAttendances = Attendance::query()
            ->whereBelongsTo($firstUser)
            ->orderBy('attendance_date')
            ->get();

        $this->assertDatabaseCount('attendances', 3);
        $this->assertCount(2, $firstUserAttendances);
        $this->assertSame('2026-09-04', $firstUserAttendances[0]->attendance_date->format('Y-m-d'));
        $this->assertSame('2026-09-04 23:59:00', $firstUserAttendances[0]->check_in_time->format('Y-m-d H:i:s'));
        $this->assertSame('2026-09-05', $firstUserAttendances[1]->attendance_date->format('Y-m-d'));
        $this->assertSame('2026-09-05 00:01:00', $firstUserAttendances[1]->check_in_time->format('Y-m-d H:i:s'));
        $this->assertDatabaseHas('attendances', [
            'user_id' => $secondUser->id,
            'attendance_date' => '2026-09-05',
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

    public function test_identical_retry_stays_deduplicated_when_an_unknown_employee_is_added_later(): void
    {
        $this->travelTo(Carbon::parse('2026-09-06 09:00:00', config('app.timezone')));

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
        $this->assertDatabaseCount('attendances', 0);
        $this->assertDatabaseHas('device_events', [
            'employee_no' => (string) $user->id,
            'is_found' => true,
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

    public function test_it_stores_hikvision_raw_multipart_event_without_content_disposition(): void
    {
        $eventLog = json_encode([
            'eventType' => 'AccessControllerEvent',
            'dateTime' => '2026-09-01T16:39:34+07:00',
            'AccessControllerEvent' => [
                'name' => 'Multipart Device User',
                'employeeNoString' => '1501',
                'cardNo' => '1234567890',
                'doorNo' => 1,
                'swipeResult' => 'success',
                'attendanceStatus' => 'checkIn',
                'currentVerifyMode' => 'cardOrFaceOrFp',
            ],
        ], JSON_THROW_ON_ERROR);
        $boundary = 'MIME_boundary';
        $body = implode("\r\n", [
            '--'.$boundary,
            'Content-Type: application/json; charset="UTF-8"',
            'Content-Length: '.strlen($eventLog),
            '',
            $eventLog,
            '--'.$boundary,
            'Content-Disposition: form-data; name="Picture"; filename="Picture.jpeg"',
            'Content-Type: image/jpeg',
            'Content-Length: 4',
            '',
            'JPEG',
            '--'.$boundary.'--',
            '',
        ]);

        $response = $this->call('POST', '/api/absensi', [], [], [], [
            'CONTENT_TYPE' => 'multipart/form-data; boundary='.$boundary,
            'CONTENT_LENGTH' => (string) strlen($body),
            'REMOTE_ADDR' => '2001:db8::1501',
        ], $body);

        $response->assertOk();

        $this->assertDatabaseHas('device_events', [
            'device_code' => 'HQ-BIO-01',
            'source_ip' => '2001:db8::1501',
            'event_type' => 'AccessControllerEvent',
            'employee_no' => '1501',
            'name' => 'Multipart Device User',
            'card_no' => '1234567890',
            'door_no' => '1',
            'swipe_result' => 'success',
            'attendance_status' => 'checkIn',
            'verify_mode' => 'cardOrFaceOrFp',
            'status' => 'received',
        ]);
    }

    public function test_it_stores_event_log_uploaded_as_a_multipart_file(): void
    {
        $eventLog = json_encode([
            'eventType' => 'AccessControllerEvent',
            'dateTime' => '2026-09-01T16:40:00+07:00',
            'AccessControllerEvent' => [
                'name' => 'Uploaded Event User',
                'employeeNoString' => '1502',
                'attendanceStatus' => 'checkOut',
                'currentVerifyMode' => 'cardOrFaceOrFp',
            ],
        ], JSON_THROW_ON_ERROR);

        $response = $this->post('/api/absensi', [
            'event_log' => UploadedFile::fake()->createWithContent('event.json', $eventLog),
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('device_events', [
            'event_type' => 'AccessControllerEvent',
            'employee_no' => '1502',
            'name' => 'Uploaded Event User',
            'attendance_status' => 'checkOut',
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
        $this->travelTo(Carbon::parse('2026-09-07 08:00:00', config('app.timezone')));

        $user = $this->createUser();
        $payload = $this->attendancePayload($user, 'checkOut', '2025-10-30T14:55:00+07:00');

        $this->postJson('/api/absensi', $payload)->assertOk();

        $this->travelTo(Carbon::parse('2026-09-07 09:00:00', config('app.timezone')));

        $this->postJson('/api/absensi', $payload)->assertOk();

        $this->assertDatabaseCount('device_events', 1);
        $this->assertDatabaseCount('attendances', 1);
        $this->assertDatabaseHas('device_events', [
            'employee_no' => (string) $user->id,
            'name' => $user->name,
            'is_found' => true,
            'attendance_status' => 'checkOut',
        ]);

        $deviceEvent = DeviceEvent::query()->firstOrFail();
        $attendance = Attendance::query()->firstOrFail();

        $this->assertSame($deviceEvent->id, $attendance->device_event_id);
        $this->assertNull($attendance->attendance_status);
        $this->assertSame('2026-09-07', $attendance->attendance_date->format('Y-m-d'));
        $this->assertSame('2026-09-07 08:00:00', $attendance->check_in_time->format('Y-m-d H:i:s'));
        $this->assertNull($attendance->check_out_time);
    }

    public function test_legacy_attendance_types_and_duplicate_null_dates_remain_supported(): void
    {
        $user = $this->createUser();

        foreach (['gym', 'pt', 'visit', 'coach_attendance'] as $index => $type) {
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
                'attendance_date' => null,
            ]);
        }

        $this->assertDatabaseCount('attendances', 4);
    }

    public function test_existing_manual_attendances_update_the_earliest_row_without_processing_booking(): void
    {
        $this->travelTo(Carbon::parse('2026-09-08 10:00:00', config('app.timezone')));

        $member = $this->createUser();
        $membership = $this->createPtMembership($member, ['remaining_sessions' => 3]);
        $booking = $this->createPtBooking($member, $membership);
        $earliestAttendance = Attendance::create([
            'user_id' => $member->id,
            'membership_id' => null,
            'type' => 'gym',
            'attendance_status' => null,
            'attendance_date' => null,
            'check_in_time' => '2026-09-08 07:00:00',
            'check_out_time' => null,
        ]);
        $laterAttendance = Attendance::create([
            'user_id' => $member->id,
            'membership_id' => null,
            'type' => 'gym',
            'attendance_status' => null,
            'attendance_date' => null,
            'check_in_time' => '2026-09-08 08:00:00',
            'check_out_time' => null,
        ]);

        $this->postJson('/api/absensi', $this->attendancePayload(
            $member,
            'checkOut',
            '2030-01-01T23:00:00+07:00',
        ))->assertOk();

        $this->assertDatabaseCount('attendances', 2);
        $this->assertSame('2026-09-08 07:00:00', $earliestAttendance->fresh()->check_in_time->format('Y-m-d H:i:s'));
        $this->assertSame('2026-09-08 10:00:00', $earliestAttendance->fresh()->check_out_time->format('Y-m-d H:i:s'));
        $this->assertNull($earliestAttendance->fresh()->device_event_id);
        $this->assertNull($laterAttendance->fresh()->check_out_time);
        $this->assertSame('not_yet', $booking->fresh()->attendance);
        $this->assertSame(3, $membership->fresh()->remaining_sessions);
        $this->assertDatabaseMissing('attendances', [
            'user_id' => $member->id,
            'attendance_date' => '2026-09-08',
        ]);
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

    public function test_first_check_out_marks_a_booking_attended_and_reduces_sessions(): void
    {
        $this->travelTo(Carbon::parse('2026-09-09 17:00:00', config('app.timezone')));

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

        $attendance = Attendance::query()->whereBelongsTo($member)->firstOrFail();

        $this->assertSame('2026-09-09 17:00:00', $attendance->check_in_time->format('Y-m-d H:i:s'));
        $this->assertNull($attendance->check_out_time);
    }

    public function test_check_out_after_check_in_does_not_process_another_booking(): void
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

        $this->assertSame('not_yet', $afternoonBooking->fresh()->attendance);
        $this->assertSame(2, $membership->fresh()->remaining_sessions);

        $this->travelTo(Carbon::parse('2026-07-19 16:00:00', config('app.timezone')));

        $this->postJson('/api/absensi', $this->attendancePayload(
            $member,
            'checkIn',
            '2030-01-03T23:02:00+07:00',
        ))->assertOk();

        $this->assertSame('not_yet', $afternoonBooking->fresh()->attendance);
        $this->assertSame(2, $membership->fresh()->remaining_sessions);
        $this->assertDatabaseCount('device_events', 3);
        $this->assertDatabaseCount('attendances', 1);

        $attendance = Attendance::query()->whereBelongsTo($member)->firstOrFail();

        $this->assertSame('2026-07-19 07:00:00', $attendance->check_in_time->format('Y-m-d H:i:s'));
        $this->assertSame('2026-07-19 16:00:00', $attendance->check_out_time->format('Y-m-d H:i:s'));
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

    public function test_distinct_check_in_then_check_out_processes_only_one_booking(): void
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

        $this->travelTo(Carbon::parse('2026-07-19 09:00:00', config('app.timezone')));

        $this->postJson('/api/absensi', $this->attendancePayload(
            $member,
            'checkOut',
            '2025-01-07T09:00:00+07:00',
        ))->assertOk();

        $this->assertSame('attended', $firstBooking->fresh()->attendance);
        $this->assertSame('not_yet', $secondBooking->fresh()->attendance);
        $this->assertSame(2, $membership->fresh()->remaining_sessions);
        $this->assertDatabaseCount('device_events', 2);
        $this->assertDatabaseCount('attendances', 1);

        $attendance = Attendance::query()->whereBelongsTo($member)->firstOrFail();

        $this->assertSame('2026-07-19 08:00:00', $attendance->check_in_time->format('Y-m-d H:i:s'));
        $this->assertSame('2026-07-19 09:00:00', $attendance->check_out_time->format('Y-m-d H:i:s'));
    }

    public function test_member_repeated_check_out_updates_checkout_without_processing_another_booking(): void
    {
        $this->travelTo(Carbon::parse('2026-07-19 08:00:00', config('app.timezone')));

        $member = $this->createUser();
        $membership = $this->createPtMembership($member, ['remaining_sessions' => 3]);
        $firstBooking = $this->createPtBooking($member, $membership, ['booking_time' => '08:00:00']);
        $secondBooking = $this->createPtBooking($member, $membership, ['booking_time' => '09:00:00']);

        $this->postJson('/api/absensi', $this->attendancePayload(
            $member,
            'checkOut',
            '2025-01-08T17:00:00+07:00',
        ))->assertOk();

        $this->travelTo(Carbon::parse('2026-07-19 09:00:00', config('app.timezone')));

        $this->postJson('/api/absensi', $this->attendancePayload(
            $member,
            'checkOut',
            '2025-01-08T19:00:00+07:00',
        ))->assertOk();

        $this->assertSame('attended', $firstBooking->fresh()->attendance);
        $this->assertSame('not_yet', $secondBooking->fresh()->attendance);
        $this->assertSame(2, $membership->fresh()->remaining_sessions);

        $attendance = Attendance::query()->whereBelongsTo($member)->firstOrFail();

        $this->assertSame('2026-07-19 08:00:00', $attendance->check_in_time->format('Y-m-d H:i:s'));
        $this->assertSame('2026-07-19 09:00:00', $attendance->check_out_time->format('Y-m-d H:i:s'));
        $this->assertDatabaseCount('device_events', 2);
        $this->assertDatabaseCount('attendances', 1);
    }

    public function test_check_out_first_then_check_in_processes_only_one_booking(): void
    {
        $this->travelTo(Carbon::parse('2026-07-19 08:00:00', config('app.timezone')));

        $member = $this->createUser();
        $membership = $this->createPtMembership($member, ['remaining_sessions' => 3]);
        $firstBooking = $this->createPtBooking($member, $membership, ['booking_time' => '08:00:00']);
        $secondBooking = $this->createPtBooking($member, $membership, ['booking_time' => '09:00:00']);

        $this->postJson('/api/absensi', $this->attendancePayload(
            $member,
            'checkOut',
            '2025-01-09T17:00:00+07:00',
        ))->assertOk();

        $this->travelTo(Carbon::parse('2026-07-19 09:00:00', config('app.timezone')));

        $this->postJson('/api/absensi', $this->attendancePayload(
            $member,
            'checkIn',
            '2025-01-09T08:00:00+07:00',
        ))->assertOk();

        $this->assertSame('attended', $firstBooking->fresh()->attendance);
        $this->assertSame('not_yet', $secondBooking->fresh()->attendance);
        $this->assertSame(2, $membership->fresh()->remaining_sessions);

        $attendance = Attendance::query()->whereBelongsTo($member)->firstOrFail();

        $this->assertSame('2026-07-19 08:00:00', $attendance->check_in_time->format('Y-m-d H:i:s'));
        $this->assertSame('2026-07-19 09:00:00', $attendance->check_out_time->format('Y-m-d H:i:s'));
        $this->assertDatabaseCount('device_events', 2);
        $this->assertDatabaseCount('attendances', 1);
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
