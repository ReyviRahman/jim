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

        $response = $this->call('POST', '/api/integrations/devices/HQ-BIO-01/event', [], [], [], [
            'CONTENT_TYPE' => 'application/xml',
        ], $xml);

        $response->assertStatus(200);
        $response->assertSee('OK');

        $this->assertDatabaseHas('device_events', [
            'device_code' => 'HQ-BIO-01',
            'event_type' => 'AccessControllerEvent',
            'status' => 'received',
        ]);
    }

    public function test_it_stores_json_event(): void
    {
        $payload = [
            'eventType' => 'VideoMotion',
            'eventState' => 'active',
            'dateTime' => '2025-10-30T14:35:00Z',
        ];

        $response = $this->postJson('/api/integrations/devices/HQ-BIO-01/event', $payload);

        $response->assertStatus(200);

        $this->assertDatabaseHas('device_events', [
            'device_code' => 'HQ-BIO-01',
            'event_type' => 'VideoMotion',
            'status' => 'received',
        ]);
    }

    public function test_it_returns_ok_and_logs_failed_status_for_invalid_xml(): void
    {
        $response = $this->call('POST', '/api/integrations/devices/HQ-BIO-01/event', [], [], [], [
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
