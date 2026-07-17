<?php

namespace Tests\Feature;

use App\Models\DeviceEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
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
        $response->assertSee('Sinkronkan Member Terbaru');
    }

    public function test_it_syncs_the_most_recent_member_to_hikvision(): void
    {
        $olderMember = $this->createUser([
            'role' => 'member',
            'created_at' => now()->subDay(),
        ]);
        $latestMember = $this->createUser(['role' => 'member']);
        $this->createUser(['role' => 'admin']);

        config()->set('services.hikvision', [
            'base_url' => 'http://hikvision.test',
            'username' => 'admin',
            'password' => 'secret',
            'timeout' => 10,
            'connect_timeout' => 5,
            'user_endpoint' => '/ISAPI/AccessControl/UserInfo/Record?format=json',
        ]);
        Http::preventStrayRequests();
        Http::fake(['http://hikvision.test/*' => Http::response([], 200)]);

        Livewire::test('pages::device-events')
            ->call('syncLatestMember')
            ->assertSee("Member {$latestMember->name} (ID: {$latestMember->id}) berhasil dikirim ke Hikvision.");

        Http::assertSent(function (Request $request) use ($latestMember): bool {
            return $request->method() === 'POST'
                && $request->url() === 'http://hikvision.test/ISAPI/AccessControl/UserInfo/Record?format=json'
                && $request->data() === [
                    'UserInfo' => [
                        'employeeNo' => (string) $latestMember->id,
                        'name' => $latestMember->name,
                        'userType' => 'normal',
                        'Valid' => [
                            'enable' => true,
                            'beginTime' => now()->startOfYear()->format('Y-m-d\\TH:i:s'),
                            'endTime' => now()->endOfYear()->format('Y-m-d\\TH:i:s'),
                        ],
                    ],
                ];
        });
        $this->assertNotSame($olderMember->id, $latestMember->id);
    }

    public function test_it_does_not_send_a_request_when_no_member_exists(): void
    {
        $this->createUser(['role' => 'admin']);
        Http::preventStrayRequests();

        Livewire::test('pages::device-events')
            ->call('syncLatestMember')
            ->assertSee('Belum ada member yang dapat disinkronkan.');

        Http::assertNothingSent();
    }

    public function test_it_reports_a_hikvision_failure_without_throwing(): void
    {
        $this->createUser(['role' => 'member']);

        config()->set('services.hikvision', [
            'base_url' => 'http://hikvision.test',
            'username' => 'admin',
            'password' => 'secret',
            'timeout' => 10,
            'connect_timeout' => 5,
            'user_endpoint' => '/ISAPI/AccessControl/UserInfo/Record?format=json',
        ]);
        Http::preventStrayRequests();
        Http::fake(['http://hikvision.test/*' => Http::response([], 500)]);

        Livewire::test('pages::device-events')
            ->call('syncLatestMember')
            ->assertSee('Gagal mengirim member ke Hikvision. Periksa koneksi dan konfigurasi perangkat.');

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
