<?php

namespace Tests\Feature;

use App\HikvisionUserService;
use App\Jobs\SyncHikvisionMember;
use App\Models\DeviceEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
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
                    'UserInfo' => [
                        ['employeeNo' => '10'],
                        ['UserInfo' => ['employeeNo' => '12']],
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

    public function test_it_treats_a_hikvision_no_match_response_as_an_empty_result(): void
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
                    'responseStatusStrg' => 'NO MATCH',
                ],
            ], 200),
        ]);

        $employeeNumbers = app(HikvisionUserService::class)->existingEmployeeNumbers([10]);

        $this->assertSame([], $employeeNumbers);
    }

    public function test_it_rejects_a_hikvision_search_protocol_error_response(): void
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
                'ResponseStatus' => [
                    'statusCode' => 6,
                    'statusString' => 'Invalid Content',
                ],
            ], 200),
        ]);

        $this->expectException(\RuntimeException::class);

        app(HikvisionUserService::class)->existingEmployeeNumbers([10]);
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
        Http::fake(['http://hikvision.test/*' => Http::response([], 200)]);

        Livewire::test('pages::dashboard.admin.akun.member.index')
            ->call('openSyncModal', $member->id)
            ->assertSet('showSyncModal', true)
            ->set('syncStartDate', '2026-07-01')
            ->set('syncEndDate', '2026-07-31')
            ->call('syncMember')
            ->assertSet('showSyncModal', false)
            ->assertSet('hikvisionEmployeeNumbers', [(string) $member->id])
            ->assertDontSee("openSyncModal({$member->id})")
            ->assertSee("Member {$member->name} (ID: {$member->id}) berhasil dikirim ke Hikvision.");

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request->url() === 'http://hikvision.test/ISAPI/AccessControl/UserInfo/Record?format=json'
            && $request->data()['UserInfo']['Valid'] === [
                'enable' => true,
                'beginTime' => '2026-07-01T00:00:00',
                'endTime' => '2026-07-31T23:59:59',
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
                    'UserInfo' => ['employeeNo' => (string) $syncedMember->id],
                ],
            ], 200),
        ]);

        Livewire::test('pages::dashboard.admin.akun.member.index')
            ->assertDontSee("openSyncModal({$syncedMember->id})")
            ->assertSee("openSyncModal({$unsyncedMember->id})");
    }

    public function test_member_account_page_rechecks_hikvision_before_opening_the_sync_modal(): void
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
            'http://hikvision.test/ISAPI/AccessControl/UserInfo/Search?format=json' => Http::sequence()
                ->push(['UserInfoSearch' => ['UserInfo' => []]], 200)
                ->push(['UserInfoSearch' => ['MatchList' => ['employeeNo' => (string) $member->id]]], 200),
        ]);

        Livewire::test('pages::dashboard.admin.akun.member.index')
            ->assertSee("openSyncModal({$member->id})")
            ->call('openSyncModal', $member->id)
            ->assertSet('showSyncModal', false)
            ->assertDontSee("openSyncModal({$member->id})");

        Http::assertNotSent(fn (Request $request): bool => $request->url() === 'http://hikvision.test/ISAPI/AccessControl/UserInfo/Record?format=json');
    }

    public function test_member_account_page_keeps_sync_button_when_automatic_hikvision_check_fails(): void
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

    public function test_member_account_page_queues_all_members_with_this_year_as_the_default_validity_period(): void
    {
        $firstMember = $this->createUser(['role' => 'member']);
        $secondMember = $this->createUser(['role' => 'member']);
        $thirdMember = $this->createUser(['role' => 'member']);
        $this->createUser(['role' => 'admin']);

        config()->set('services.hikvision', [
            'base_url' => 'http://hikvision.test',
            'username' => 'admin',
            'password' => 'secret',
            'timeout' => 10,
            'connect_timeout' => 5,
            'user_endpoint' => '/ISAPI/AccessControl/UserInfo/Record?format=json',
            'user_search_endpoint' => '/ISAPI/AccessControl/UserInfo/Search?format=json',
        ]);
        Queue::fake();
        Http::preventStrayRequests();
        Http::fake([
            'http://hikvision.test/ISAPI/AccessControl/UserInfo/Search?format=json' => Http::response([
                'UserInfoSearch' => ['responseStatusStrg' => 'NO MATCH'],
            ], 200),
        ]);

        Livewire::test('pages::dashboard.admin.akun.member.index')
            ->call('openBulkSyncModal')
            ->assertSet('showBulkSyncModal', true)
            ->assertSet('bulkSyncStartDate', now()->startOfYear()->toDateString())
            ->assertSet('bulkSyncEndDate', now()->endOfYear()->toDateString())
            ->call('queueBulkSync')
            ->assertSet('showBulkSyncModal', false)
            ->assertSee('3 member dijadwalkan untuk disinkronkan ke Hikvision.');

        Queue::assertPushed(SyncHikvisionMember::class, 3);
        Queue::assertPushed(SyncHikvisionMember::class, function (SyncHikvisionMember $job) use ($firstMember, $secondMember, $thirdMember): bool {
            return in_array($job->userId, [$firstMember->id, $secondMember->id, $thirdMember->id], true)
                && $job->validityStart === now()->startOfYear()->toDateString()
                && $job->validityEnd === now()->endOfYear()->toDateString()
                && $job->queue === 'hikvision';
        });
    }

    public function test_bulk_hikvision_job_skips_members_that_already_exist_on_the_device(): void
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
            'http://hikvision.test/ISAPI/AccessControl/UserInfo/Search?format=json' => Http::response([
                'UserInfoSearch' => ['UserInfo' => ['employeeNo' => (string) $member->id]],
            ], 200),
        ]);

        (new SyncHikvisionMember($member->id, '2026-01-01', '2026-12-31'))
            ->handle(app(HikvisionUserService::class));

        Http::assertNotSent(fn (Request $request): bool => $request->url() === 'http://hikvision.test/ISAPI/AccessControl/UserInfo/Record?format=json');
    }

    public function test_bulk_hikvision_job_syncs_an_unknown_member_with_the_selected_validity_period(): void
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
            'http://hikvision.test/ISAPI/AccessControl/UserInfo/Search?format=json' => Http::response([
                'UserInfoSearch' => ['responseStatusStrg' => 'NO MATCH'],
            ], 200),
            'http://hikvision.test/ISAPI/AccessControl/UserInfo/Record?format=json' => Http::response([], 200),
        ]);

        (new SyncHikvisionMember($member->id, '2026-01-01', '2026-12-31'))
            ->handle(app(HikvisionUserService::class));

        Http::assertSent(fn (Request $request): bool => $request->url() === 'http://hikvision.test/ISAPI/AccessControl/UserInfo/Record?format=json'
            && $request->data()['UserInfo']['employeeNo'] === (string) $member->id
            && $request->data()['UserInfo']['Valid'] === [
                'enable' => true,
                'beginTime' => '2026-01-01T00:00:00',
                'endTime' => '2026-12-31T23:59:59',
            ]);
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
