<?php

namespace Tests\Feature;

use App\HikvisionUserService;
use App\Jobs\SyncHikvisionMember;
use App\Models\DeviceEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
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

    public function test_it_accepts_a_flat_hikvision_success_response(): void
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
            'http://hikvision.test/ISAPI/AccessControl/UserInfo/Record?format=json' => Http::response([
                'statusCode' => 0,
                'statusString' => 'ok',
            ], 200),
        ]);

        app(HikvisionUserService::class)->sync($member);

        Http::assertSentCount(1);
    }

    public function test_it_rejects_a_malformed_hikvision_response_status(): void
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
            'http://hikvision.test/ISAPI/AccessControl/UserInfo/Record?format=json' => Http::response([
                'ResponseStatus' => 'invalid',
            ], 200),
        ]);

        try {
            app(HikvisionUserService::class)->sync($member);
            $this->fail('Expected a malformed Hikvision response to be rejected.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Hikvision member sync returned an invalid response.', $exception->getMessage());
        }

        Http::assertSentCount(1);
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

    public function test_it_stops_checking_hikvision_during_the_unavailable_cooldown(): void
    {
        config()->set('services.hikvision', [
            'base_url' => 'http://hikvision.test',
            'username' => 'admin',
            'password' => 'secret',
            'timeout' => 3,
            'connect_timeout' => 3,
            'failure_cooldown' => 60,
            'user_search_endpoint' => '/ISAPI/AccessControl/UserInfo/Search?format=json',
        ]);
        Http::preventStrayRequests();
        Http::fake(['http://hikvision.test/*' => Http::failedConnection()]);

        $service = app(HikvisionUserService::class);

        $connectionFailed = false;

        try {
            $service->existingEmployeeNumbers([10]);
            $this->fail('Expected the failed Hikvision connection to be thrown.');
        } catch (ConnectionException) {
            $connectionFailed = true;
        }

        $this->assertTrue($connectionFailed);
        $this->assertTrue(Cache::has('hikvision:unavailable:'.md5('http://hikvision.test')));

        try {
            $service->existingEmployeeNumbers([10]);
            $this->fail('Expected the cached device cooldown to block the request.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Hikvision device is temporarily unavailable.', $exception->getMessage());
        }

        Http::assertSentCount(1);
        Cache::forget('hikvision:unavailable:'.md5('http://hikvision.test'));
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
        $member = $this->createUser([
            'role' => 'member',
            'hikvision_employee_no' => '  HIK-0250  ',
        ]);

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
            'http://hikvision.test/ISAPI/AccessControl/UserInfo/Record?format=json' => Http::response([
                'ResponseStatus' => [
                    'statusCode' => 1,
                    'statusString' => 'OK',
                ],
            ], 200),
        ]);

        Livewire::test('pages::dashboard.admin.akun.member.index')
            ->call('openSyncModal', $member->id)
            ->assertSet('showSyncModal', true)
            ->set('syncStartDate', '2026-07-01')
            ->set('syncEndDate', '2026-07-31')
            ->call('syncMember')
            ->assertSet('showSyncModal', false)
            ->assertSet('syncedUserIds', [$member->id])
            ->assertDontSee("openSyncModal({$member->id})")
            ->assertSee("Member {$member->name} (ID: {$member->id}) berhasil dikirim ke Hikvision.");

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request->url() === 'http://hikvision.test/ISAPI/AccessControl/UserInfo/Record?format=json'
            && $request->data()['UserInfo']['employeeNo'] === 'HIK-0250'
            && $request->data()['UserInfo']['Valid'] === [
                'enable' => true,
                'beginTime' => '2026-07-01T00:00:00',
                'endTime' => '2026-07-31T23:59:59',
            ]);
        Http::assertSentCount(1);
    }

    public function test_member_account_page_does_not_send_a_request_when_sync_validation_fails(): void
    {
        $member = $this->createUser(['role' => 'member']);

        Http::preventStrayRequests();
        Http::fake();

        Livewire::test('pages::dashboard.admin.akun.member.index')
            ->call('openSyncModal', $member->id)
            ->set('syncStartDate', '2026-07-31')
            ->set('syncEndDate', '2026-07-01')
            ->call('syncMember')
            ->assertHasErrors(['syncEndDate' => 'after_or_equal']);

        Http::assertNothingSent();
    }

    public function test_member_account_page_does_not_send_a_request_for_an_unknown_member(): void
    {
        Http::preventStrayRequests();
        Http::fake();

        Livewire::test('pages::dashboard.admin.akun.member.index')
            ->set('syncingUserId', 999999)
            ->set('syncStartDate', '2026-07-01')
            ->set('syncEndDate', '2026-07-31')
            ->call('syncMember')
            ->assertHasErrors(['syncingUserId' => 'exists']);

        Http::assertNothingSent();
    }

    public function test_member_account_page_reports_a_hikvision_response_status_error_after_one_request(): void
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
            'http://hikvision.test/ISAPI/AccessControl/UserInfo/Record?format=json' => Http::response([
                'ResponseStatus' => [
                    'statusCode' => 6,
                    'statusString' => 'Invalid Content',
                    'subStatusCode' => 'employeeNoAlreadyExists',
                ],
            ], 200),
        ]);

        Livewire::test('pages::dashboard.admin.akun.member.index')
            ->call('openSyncModal', $member->id)
            ->call('syncMember')
            ->assertSet('showSyncModal', true)
            ->assertSee('Gagal mengirim member ke Hikvision. Periksa koneksi dan konfigurasi perangkat.');

        Http::assertSentCount(1);
        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/UserInfo/Search'));
    }

    public function test_member_account_page_does_not_check_hikvision_automatically(): void
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
        Http::fake();

        Livewire::test('pages::dashboard.admin.akun.member.index')
            ->assertSee("openSyncModal({$syncedMember->id})")
            ->assertSee("openSyncModal({$unsyncedMember->id})");

        Http::assertNothingSent();
    }

    public function test_member_account_page_opens_the_sync_modal_without_contacting_hikvision(): void
    {
        $member = $this->createUser(['role' => 'member']);

        Http::preventStrayRequests();
        Http::fake();

        Livewire::test('pages::dashboard.admin.akun.member.index')
            ->assertSee("openSyncModal({$member->id})")
            ->call('openSyncModal', $member->id)
            ->assertSet('showSyncModal', true)
            ->assertSet('syncingUserId', $member->id);

        Http::assertNothingSent();
    }

    public function test_member_account_search_does_not_check_hikvision(): void
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
        Http::fake();

        Livewire::test('pages::dashboard.admin.akun.member.index')
            ->set('search', $member->name)
            ->assertSee("openSyncModal({$member->id})");

        Http::assertNothingSent();
    }

    public function test_hikvision_jobs_are_disabled_without_affecting_the_member_page(): void
    {
        $member = $this->createUser(['role' => 'member']);
        Queue::fake();
        Http::preventStrayRequests();
        Http::fake();

        Livewire::test('pages::dashboard.admin.akun.member.index')
            ->assertDontSee('wire:click="openBulkSyncModal"', escape: false)
            ->assertDontSee('Sinkronkan Semua Member ke Hikvision')
            ->assertSee("openSyncModal({$member->id})");

        Queue::assertNothingPushed();
        Http::assertNothingSent();

        (new SyncHikvisionMember($member->id, '2026-01-01', '2026-12-31'))
            ->handle(app(HikvisionUserService::class));

        Http::assertNothingSent();
    }

    public function test_member_account_page_does_not_expose_bulk_sync_when_jobs_are_enabled(): void
    {
        $member = $this->createUser(['role' => 'member']);

        config()->set('services.hikvision.queue_enabled', true);
        Queue::fake();
        Http::preventStrayRequests();
        Http::fake();

        Livewire::test('pages::dashboard.admin.akun.member.index')
            ->assertDontSee('wire:click="openBulkSyncModal"', escape: false)
            ->assertDontSee('Sinkronkan Semua Member ke Hikvision')
            ->assertSee("openSyncModal({$member->id})");

        Queue::assertNothingPushed();
        Http::assertNothingSent();
    }

    public function test_bulk_hikvision_job_skips_members_that_already_exist_on_the_device(): void
    {
        $member = $this->createUser([
            'role' => 'member',
            'hikvision_employee_no' => 'HIK-JOB-EXISTING',
        ]);

        config()->set('services.hikvision', [
            'queue_enabled' => true,
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
                'UserInfoSearch' => ['UserInfo' => ['employeeNo' => 'HIK-JOB-EXISTING']],
            ], 200),
        ]);

        (new SyncHikvisionMember($member->id, '2026-01-01', '2026-12-31'))
            ->handle(app(HikvisionUserService::class));

        Http::assertNotSent(fn (Request $request): bool => $request->url() === 'http://hikvision.test/ISAPI/AccessControl/UserInfo/Record?format=json');
        Http::assertSent(fn (Request $request): bool => $request->data()['UserInfoSearchCond']['EmployeeNoList'] === [
            ['employeeNo' => 'HIK-JOB-EXISTING'],
        ]);
    }

    public function test_bulk_hikvision_job_syncs_an_unknown_member_with_the_selected_validity_period(): void
    {
        $member = $this->createUser(['role' => 'member']);

        config()->set('services.hikvision', [
            'queue_enabled' => true,
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
