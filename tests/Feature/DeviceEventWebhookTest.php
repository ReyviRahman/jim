<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $user = User::factory()->create([
            'age' => 30,
            'gender' => 'Laki-laki',
            'phone' => fake()->unique()->numerify('08##########'),
        ]);

        $response = $this->postJson('/api/absensi', [
            'eventType' => 'AccessControllerEvent',
            'dateTime' => '2025-10-30T14:32:00+07:00',
            'AccessControllerEvent' => [
                'employeeNoString' => (string) $user->id,
                'name' => $user->name,
                'attendanceStatus' => 'checkIn',
                'currentVerifyMode' => 'cardOrFaceOrFp',
            ],
        ]);

        $response->assertOk();

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
        $payload = [
            'eventType' => 'AccessControllerEvent',
            'dateTime' => '2025-10-30T14:55:00+07:00',
            'AccessControllerEvent' => [
                'employeeNoString' => 'EMP003',
                'name' => 'Retry Employee',
                'attendanceStatus' => 'checkOut',
                'currentVerifyMode' => 'cardOrFaceOrFp',
            ],
        ];

        $this->postJson('/api/absensi', $payload)->assertOk();
        $this->postJson('/api/absensi', $payload)->assertOk();

        $this->assertDatabaseCount('device_events', 1);
        $this->assertDatabaseHas('device_events', [
            'employee_no' => 'EMP003',
            'name' => 'Retry Employee',
            'is_found' => false,
            'attendance_status' => 'checkOut',
        ]);
    }
}
