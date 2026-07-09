<?php

namespace Tests\Feature;

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
            'event_type' => 'AccessControllerEvent',
            'employee_no' => 'EMP001',
            'name' => 'John Doe',
            'card_no' => '1234567890',
            'door_no' => '1',
            'swipe_result' => 'success',
            'status' => 'received',
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
            'event_type' => 'AccessControllerEvent',
            'employee_no' => 'EMP002',
            'name' => 'Jane Doe',
            'card_no' => '0987654321',
            'door_no' => '2',
            'swipe_result' => 'success',
            'attendance_status' => 'checkIn',
            'verify_mode' => 'cardOrFaceOrFp',
            'status' => 'received',
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
            'event_type' => 'AccessControllerEvent',
            'name' => 'Reyvi Rahman',
            'employee_no' => '126352131231',
            'door_no' => '1',
            'swipe_result' => 'success',
            'attendance_status' => 'checkOut',
            'verify_mode' => 'cardOrFaceOrFp',
            'status' => 'received',
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

    public function test_it_returns_ok_and_logs_failed_status_for_invalid_xml(): void
    {
        $response = $this->call('POST', '/api/absensi', [], [], [], [
            'CONTENT_TYPE' => 'application/xml',
        ], '<not valid xml');

        $response->assertStatus(200);

        $this->assertDatabaseHas('device_events', [
            'device_code' => 'HQ-BIO-01',
            'status' => 'failed',
            'event_type' => null,
        ]);
    }
}
