<?php

namespace Tests\Feature;

use App\HikvisionUserService;
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

    public function test_it_sends_a_member_with_a_custom_validity_period(): void
    {
        $member = $this->createUser(['role' => 'member']);

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

        app(HikvisionUserService::class)->sync(
            $member,
            now()->setDate(2026, 7, 1)->startOfDay(),
            now()->setDate(2026, 7, 31)->endOfDay(),
        );

        Http::assertSent(fn (Request $request): bool => $request->data()['UserInfo']['Valid'] === [
            'enable' => true,
            'beginTime' => '2026-07-01T00:00:00',
            'endTime' => '2026-07-31T23:59:59',
        ]);
    }

    public function test_it_finds_existing_hikvision_members_by_employee_number(): void
    {
        config()->set('services.hikvision', [
            'base_url' => 'http://hikvision.test',
            'username' => 'admin',
            'password' => 'secret',
            'timeout' => 10,
            'connect_timeout' => 5,
            'user_endpoint' => '/ISAPI/AccessControl/UserInfo/Record?format=json',
            'user_search_endpoint' => '/ISAPI/AccessControl/UserInfo/Search?format=json',
        ]);
        Http::preventStrayRequests();
        Http::fake([
            'http://hikvision.test/ISAPI/AccessControl/UserInfo/Search?format=json' => Http::response([
                'UserInfoSearch' => [
                    'MatchList' => [
                        ['employeeNo' => '10'],
                        ['employeeNo' => '12'],
                    ],
                ],
            ], 200),
        ]);

        $employeeNumbers = app(HikvisionUserService::class)->existingEmployeeNumbers([10, 11, 12]);

        $this->assertSame(['10', '12'], $employeeNumbers);
        Http::assertSent(function (Request $request): bool {
            return $request->method() === 'POST'
                && $request->url() === 'http://hikvision.test/ISAPI/AccessControl/UserInfo/Search?format=json'
                && $request->data()['UserInfoSearchCond']['searchResultPosition'] === 0
                && $request->data()['UserInfoSearchCond']['maxResults'] === 3
                && $request->data()['UserInfoSearchCond']['EmployeeNoList'] === [
                    ['employeeNo' => '10'],
                    ['employeeNo' => '11'],
                    ['employeeNo' => '12'],
                ];
        });
    }

    public function test_member_account_page_syncs_the_selected_member_with_selected_dates(): void
    {
        $member = $this->createUser(['role' => 'member']);

        config()->set('services.hikvision', [
            'base_url' => 'http://hikvision.test',
            'username' => 'admin',
            'password' => 'secret',
            'timeout' => 10,
            'connect_timeout' => 5,
            'user_endpoint' => '/ISAPI/AccessControl/UserInfo/Record?format=json',
        ]);
        Http::preventStrayRequests();
        Http::fake([
            'http://hikvision.test/ISAPI/AccessControl/UserInfo/Search?format=json' => Http::response([
                'UserInfoSearch' => ['MatchList' => []],
            ], 200),
            'http://hikvision.test/ISAPI/AccessControl/UserInfo/Record?format=json' => Http::response([], 200),
        ]);

        Livewire::test('pages::dashboard.admin.akun.member.index')
            ->call('openSyncModal', $member->id)
            ->assertSet('showSyncModal', true)
            ->set('syncStartDate', '2026-07-01')
            ->set('syncEndDate', '2026-07-31')
            ->call('syncMember')
            ->assertSet('showSyncModal', false)
            ->assertSee("Member {$member->name} (ID: {$member->id}) berhasil dikirim ke Hikvision.")
            ->assertDontSee("openSyncModal({$member->id})");

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request->url() === 'http://hikvision.test/ISAPI/AccessControl/UserInfo/Record?format=json'
            && $request->data()['UserInfo'] === [
                'employeeNo' => (string) $member->id,
                'name' => $member->name,
                'userType' => 'normal',
                'Valid' => [
                    'enable' => true,
                    'beginTime' => '2026-07-01T00:00:00',
                    'endTime' => '2026-07-31T23:59:59',
                ],
            ]);
    }

    public function test_member_account_page_hides_the_sync_button_for_members_found_on_hikvision(): void
    {
        $syncedMember = $this->createUser(['role' => 'member']);
        $unsyncedMember = $this->createUser(['role' => 'member']);

        config()->set('services.hikvision', [
            'base_url' => 'http://hikvision.test',
            'username' => 'admin',
            'password' => 'secret',
            'timeout' => 10,
            'connect_timeout' => 5,
            'user_endpoint' => '/ISAPI/AccessControl/UserInfo/Record?format=json',
            'user_search_endpoint' => '/ISAPI/AccessControl/UserInfo/Search?format=json',
        ]);
        Http::preventStrayRequests();
        Http::fake([
            'http://hikvision.test/ISAPI/AccessControl/UserInfo/Search?format=json' => Http::response([
                'UserInfoSearch' => [
                    'MatchList' => [['employeeNo' => (string) $syncedMember->id]],
                ],
            ], 200),
        ]);

        Livewire::test('pages::dashboard.admin.akun.member.index')
            ->assertDontSee("openSyncModal({$syncedMember->id})")
            ->assertSee("openSyncModal({$unsyncedMember->id})");
    }

    public function test_member_account_page_keeps_sync_button_when_hikvision_check_fails(): void
    {
        $member = $this->createUser(['role' => 'member']);

        config()->set('services.hikvision', [
            'base_url' => 'http://hikvision.test',
            'username' => 'admin',
            'password' => 'secret',
            'timeout' => 10,
            'connect_timeout' => 5,
            'user_endpoint' => '/ISAPI/AccessControl/UserInfo/Record?format=json',
            'user_search_endpoint' => '/ISAPI/AccessControl/UserInfo/Search?format=json',
        ]);
        Http::preventStrayRequests();
        Http::fake([
            'http://hikvision.test/ISAPI/AccessControl/UserInfo/Search?format=json' => Http::response([], 500),
        ]);

        Livewire::test('pages::dashboard.admin.akun.member.index')
            ->assertSee("openSyncModal({$member->id})");
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
