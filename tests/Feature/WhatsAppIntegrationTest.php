<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WhatsAppIntegration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

class WhatsAppIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();

        config()->set([
            'services.meta_whatsapp.app_id' => '123456789',
            'services.meta_whatsapp.app_secret' => 'server-only-app-secret',
            'services.meta_whatsapp.login_config_id' => '987654321',
            'services.meta_whatsapp.graph_version' => 'v25.0',
            'services.meta_whatsapp.graph_base_url' => 'https://graph.facebook.com',
            'services.meta_whatsapp.recipient' => '6281372157714',
            'services.meta_whatsapp.template_name' => 'laporan_transaksi',
            'services.meta_whatsapp.template_language' => 'id',
            'services.meta_whatsapp.timeout' => 5,
            'services.meta_whatsapp.connect_timeout' => 2,
        ]);
    }

    public function test_settings_route_is_admin_only_and_does_not_expose_secrets(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $headCoach = User::factory()->headCoach()->create();

        WhatsAppIntegration::query()->create([
            'waba_id' => '111111',
            'phone_number_id' => '222222',
            'display_phone_number' => '+62 812 0000 0000',
            'verified_name' => 'JIM',
            'access_token' => 'server-only-business-token',
            'status' => WhatsAppIntegration::STATUS_CONNECTED,
            'connected_by_user_id' => $admin->id,
            'connected_at' => now(),
            'last_verified_at' => now(),
        ]);

        $this->get(route('admin.whatsapp.settings'))->assertRedirect(route('login'));
        $this->actingAs($headCoach)->get(route('admin.whatsapp.settings'))->assertRedirect(route('home'));

        $this->actingAs($admin)
            ->get(route('admin.whatsapp.settings'))
            ->assertOk()
            ->assertSee('Pengaturan WhatsApp Business')
            ->assertDontSee('server-only-app-secret')
            ->assertDontSee('server-only-business-token');
    }

    public function test_non_admin_cannot_mount_the_connection_component(): void
    {
        $headCoach = User::factory()->headCoach()->create();

        Livewire::actingAs($headCoach)
            ->test('pages::dashboard.admin.pengaturan.whatsapp')
            ->assertForbidden();

        Http::assertNothingSent();
    }

    public function test_admin_can_complete_embedded_signup_and_token_is_encrypted(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->fakeSuccessfulSignup();

        Livewire::actingAs($admin)
            ->test('pages::dashboard.admin.pengaturan.whatsapp')
            ->set('twoStepPin', '123456')
            ->call('completeWhatsAppSignup', 'authorization-code', '111111', '222222')
            ->assertHasNoErrors()
            ->assertSee('Nomor WhatsApp Business berhasil dihubungkan melalui Meta.');

        $integration = WhatsAppIntegration::current();

        $this->assertNotNull($integration);
        $this->assertSame('business-access-token', $integration->access_token);
        $this->assertSame('111111', $integration->waba_id);
        $this->assertSame('222222', $integration->phone_number_id);
        $this->assertSame(WhatsAppIntegration::STATUS_CONNECTED, $integration->status);
        $this->assertNotSame(
            'business-access-token',
            DB::table('whatsapp_integrations')->value('access_token'),
        );
        $this->assertFalse(Schema::hasColumn('whatsapp_integrations', 'two_step_pin'));

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://graph.facebook.com/v25.0/222222/register'
                && $request['pin'] === '123456'
                && $request['messaging_product'] === 'whatsapp'
                && $request->hasHeader('Authorization', 'Bearer business-access-token');
        });
    }

    public function test_signup_resolves_the_only_phone_and_registers_it(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->fakeSuccessfulSignup();

        Livewire::actingAs($admin)
            ->test('pages::dashboard.admin.pengaturan.whatsapp')
            ->set('twoStepPin', '123456')
            ->call('completeWhatsAppSignup', 'authorization-code', '111111', null)
            ->assertSee('Nomor WhatsApp Business berhasil dihubungkan melalui Meta.');

        Http::assertSent(
            fn (Request $request): bool => str_ends_with($request->url(), '/222222/register'),
        );
    }

    public function test_signup_rejects_token_without_required_scopes(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        Http::fake([
            'https://graph.facebook.com/v25.0/oauth/access_token' => Http::response([
                'access_token' => 'business-access-token',
            ]),
            'https://graph.facebook.com/v25.0/debug_token*' => Http::response([
                'data' => [
                    'is_valid' => true,
                    'app_id' => '123456789',
                    'scopes' => [
                        'whatsapp_business_management',
                        'whatsapp_business_messaging',
                    ],
                ],
            ]),
        ]);

        Livewire::actingAs($admin)
            ->test('pages::dashboard.admin.pengaturan.whatsapp')
            ->set('twoStepPin', '123456')
            ->call('completeWhatsAppSignup', 'authorization-code', '111111', '222222')
            ->assertSee('Izin WhatsApp Business pada access token belum lengkap.');

        $this->assertNull(WhatsAppIntegration::current());
    }

    public function test_signup_reports_connection_timeout_without_leaking_secret(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        Http::fake([
            'https://graph.facebook.com/v25.0/oauth/access_token' => Http::failedConnection(),
        ]);

        Livewire::actingAs($admin)
            ->test('pages::dashboard.admin.pengaturan.whatsapp')
            ->set('twoStepPin', '123456')
            ->call('completeWhatsAppSignup', 'authorization-code', '111111', '222222')
            ->assertSee('Tidak dapat terhubung ke layanan Meta. Coba beberapa saat lagi.');

        $this->assertNull(WhatsAppIntegration::current());
    }

    public function test_meta_server_error_is_sanitized_without_automatic_retry(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        Http::fake([
            'https://graph.facebook.com/v25.0/oauth/access_token' => Http::response([
                'error' => [
                    'message' => 'Internal detail containing server-only-app-secret',
                ],
            ], 500),
        ]);

        Livewire::actingAs($admin)
            ->test('pages::dashboard.admin.pengaturan.whatsapp')
            ->set('twoStepPin', '123456')
            ->call('completeWhatsAppSignup', 'authorization-code', '111111', '222222')
            ->assertSee('Proses koneksi WhatsApp ke Meta gagal.')
            ->assertDontSee('server-only-app-secret');

        Http::assertSentCount(1);
        $this->assertNull(WhatsAppIntegration::current());
    }

    public function test_admin_can_verify_and_refresh_connected_phone_metadata(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $integration = WhatsAppIntegration::query()->create([
            'waba_id' => '111111',
            'phone_number_id' => '222222',
            'display_phone_number' => '+62 812 0000 0000',
            'verified_name' => 'JIM Lama',
            'access_token' => 'business-access-token',
            'status' => WhatsAppIntegration::STATUS_CONNECTED,
            'connected_by_user_id' => $admin->id,
            'connected_at' => now(),
        ]);

        Http::fake([
            'https://graph.facebook.com/v25.0/222222*' => Http::response([
                'id' => '222222',
                'display_phone_number' => '+62 813 0000 0000',
                'verified_name' => 'JIM Baru',
            ]),
        ]);

        Livewire::actingAs($admin)
            ->test('pages::dashboard.admin.pengaturan.whatsapp')
            ->call('checkConnection')
            ->assertSee('Koneksi WhatsApp ke Meta masih aktif.');

        $integration->refresh();

        $this->assertSame('+62 813 0000 0000', $integration->display_phone_number);
        $this->assertSame('JIM Baru', $integration->verified_name);
        $this->assertNotNull($integration->last_verified_at);

        Http::assertSent(fn (Request $request): bool => str_contains(
            $request->url(),
            'fields=id%2Cdisplay_phone_number%2Cverified_name',
        ));
    }

    public function test_invalid_token_marks_connection_for_reconnect(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $integration = WhatsAppIntegration::query()->create([
            'waba_id' => '111111',
            'phone_number_id' => '222222',
            'access_token' => 'expired-token',
            'status' => WhatsAppIntegration::STATUS_CONNECTED,
            'connected_by_user_id' => $admin->id,
            'connected_at' => now(),
        ]);

        Http::fake([
            'https://graph.facebook.com/v25.0/222222*' => Http::response([
                'error' => ['code' => 190, 'message' => 'Invalid OAuth token'],
            ], 401),
        ]);

        Livewire::actingAs($admin)
            ->test('pages::dashboard.admin.pengaturan.whatsapp')
            ->call('checkConnection')
            ->assertSee('Pemeriksaan koneksi WhatsApp gagal. Kode Meta: 190.');

        $this->assertSame(
            WhatsAppIntegration::STATUS_NEEDS_RECONNECT,
            $integration->refresh()->status,
        );
    }

    public function test_embedded_signup_javascript_contract_is_page_scoped_and_safe(): void
    {
        $javascript = file_get_contents(resource_path('js/whatsapp-embedded-signup.js'));
        $view = file_get_contents(resource_path('views/pages/dashboard/admin/pengaturan/⚡whatsapp.blade.php'));

        $this->assertIsString($javascript);
        $this->assertIsString($view);
        $this->assertStringContainsString("const facebookSdkId = 'facebook-jssdk';", $javascript);
        $this->assertStringContainsString("'https://www.facebook.com'", $javascript);
        $this->assertStringContainsString("'https://web.facebook.com'", $javascript);
        $this->assertStringContainsString("featureType: 'whatsapp_business_app_onboarding'", $javascript);
        $this->assertStringContainsString("sessionInfoVersion: '3'", $javascript);
        $this->assertStringContainsString("response_type: 'code'", $javascript);
        $this->assertSame(2, substr_count($javascript, 'fedCM: false'));
        $this->assertStringContainsString('hasSubmitted', $javascript);
        $this->assertStringContainsString("window.removeEventListener('message', handleMetaMessage)", $javascript);
        $this->assertStringContainsString('data-whatsapp-embedded-signup', $view);
        $this->assertStringNotContainsString('META_APP_SECRET', $javascript);
        $this->assertStringNotContainsString('data-meta-app-secret', $view);
    }

    private function fakeSuccessfulSignup(): void
    {
        Http::fake([
            'https://graph.facebook.com/v25.0/oauth/access_token' => Http::response([
                'access_token' => 'business-access-token',
                'expires_in' => 3600,
            ]),
            'https://graph.facebook.com/v25.0/debug_token*' => Http::response([
                'data' => [
                    'is_valid' => true,
                    'app_id' => '123456789',
                    'scopes' => [
                        'business_management',
                        'whatsapp_business_management',
                        'whatsapp_business_messaging',
                    ],
                ],
            ]),
            'https://graph.facebook.com/v25.0/111111?*' => Http::response([
                'id' => '111111',
                'name' => 'JIM WABA',
            ]),
            'https://graph.facebook.com/v25.0/111111/phone_numbers*' => Http::response([
                'data' => [[
                    'id' => '222222',
                    'display_phone_number' => '+62 812 0000 0000',
                    'verified_name' => 'JIM',
                ]],
            ]),
            'https://graph.facebook.com/v25.0/222222/register' => Http::response([
                'success' => true,
            ]),
        ]);
    }
}
