<?php

namespace Tests\Feature;

use App\Models\DeviceEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeviceEventMonitoringTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_monitoring_page_displays_device_events(): void
    {
        DeviceEvent::create([
            'device_code' => 'HQ-BIO-01',
            'event_type' => 'AccessControllerEvent',
            'payload' => '<EventNotificationAlert><eventType>AccessControllerEvent</eventType></EventNotificationAlert>',
        ]);

        $response = $this->get('/device-events');

        $response->assertStatus(200);
        $response->assertSee('HQ-BIO-01');
        $response->assertSee('AccessControllerEvent');
    }
}
