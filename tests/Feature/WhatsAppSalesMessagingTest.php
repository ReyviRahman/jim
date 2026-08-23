<?php

namespace Tests\Feature;

use App\Models\MembershipTransaction;
use App\Models\User;
use App\Models\WhatsAppIntegration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class WhatsAppSalesMessagingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();

        config()->set([
            'services.meta_whatsapp.graph_version' => 'v25.0',
            'services.meta_whatsapp.graph_base_url' => 'https://graph.facebook.com',
            'services.meta_whatsapp.recipient' => '6281372157714',
            'services.meta_whatsapp.template_name' => 'laporan_transaksi',
            'services.meta_whatsapp.template_language' => 'id',
            'services.meta_whatsapp.timeout' => 5,
            'services.meta_whatsapp.connect_timeout' => 2,
        ]);
    }

    #[DataProvider('authorizedActors')]
    public function test_authorized_sales_actors_can_send_the_selected_transaction(string $role, bool $isHeadCoach): void
    {
        $actorFactory = $isHeadCoach
            ? User::factory()->headCoach()
            : User::factory()->state(['role' => $role]);
        $actor = $actorFactory->create(['shift' => 'Pagi']);
        $transaction = $this->createTransaction($actor, 'INV-SELECTED-'.$role, 175000);
        $this->createConnectedIntegration($actor);

        Http::fake([
            'https://graph.facebook.com/v25.0/222222/messages' => Http::response([
                'messages' => [['id' => 'wamid.role-'.$role]],
            ]),
        ]);

        Livewire::actingAs($actor)
            ->test('pages::dashboard.admin.penjualan.index')
            ->call('sendWhatsApp', $transaction->id)
            ->assertSee("Data transaksi {$transaction->invoice_number} berhasil dikirim.")
            ->assertSee('wamid.role-');

        Http::assertSent(function (Request $request) use ($transaction): bool {
            $parameters = collect($request['template']['components'][0]['parameters'])
                ->pluck('text')
                ->all();
            $expectedParameters = [
                $transaction->invoice_number,
                'Member '.$transaction->invoice_number,
                '23 Aug 2026',
                '-',
                '-',
                'Pemasukan Lain',
                'Paket '.$transaction->invoice_number,
                'Catatan '.$transaction->invoice_number,
                'Rp 175.000',
                'QRIS',
                $transaction->admin->name,
                '-',
                '-',
            ];

            return $request->url() === 'https://graph.facebook.com/v25.0/222222/messages'
                && $request['to'] === '6281372157714'
                && $request['type'] === 'template'
                && $request['template']['name'] === 'laporan_transaksi'
                && $request['template']['language']['code'] === 'id'
                && $parameters === $expectedParameters
                && $request->hasHeader('Authorization', 'Bearer business-access-token');
        });
    }

    public function test_clicking_a_row_sends_only_that_rows_data(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'shift' => 'Pagi']);
        $first = $this->createTransaction($admin, 'INV-FIRST', 100000);
        $second = $this->createTransaction($admin, 'INV-SECOND', 250000);
        $this->createConnectedIntegration($admin);

        Http::fake([
            'https://graph.facebook.com/v25.0/222222/messages' => Http::response([
                'messages' => [['id' => 'wamid.second']],
            ]),
        ]);

        Livewire::actingAs($admin)
            ->test('pages::dashboard.admin.penjualan.index')
            ->call('sendWhatsApp', $second->id)
            ->assertSee('Data transaksi INV-SECOND berhasil dikirim.');

        Http::assertSent(function (Request $request) use ($first, $second): bool {
            $parameterTexts = collect($request['template']['components'][0]['parameters'])->pluck('text');

            return $parameterTexts->contains($second->invoice_number)
                && $parameterTexts->contains('Rp 250.000')
                && ! $parameterTexts->contains($first->invoice_number)
                && ! $parameterTexts->contains('Rp 100.000');
        });
    }

    public function test_unauthorized_accounts_cannot_call_send_action(): void
    {
        $unauthorizedUsers = [
            User::factory()->create(['role' => 'member']),
            User::factory()->create(['role' => 'head_coach']),
        ];
        $admin = User::factory()->create(['role' => 'admin']);
        $transaction = $this->createTransaction($admin, 'INV-DENIED', 50000);
        $this->createConnectedIntegration($admin);

        foreach ($unauthorizedUsers as $unauthorizedUser) {
            Livewire::actingAs($unauthorizedUser)
                ->test('pages::dashboard.admin.penjualan.index')
                ->call('sendWhatsApp', $transaction->id)
                ->assertForbidden();
        }

        Http::assertNothingSent();
    }

    public function test_disconnected_integration_prevents_sending(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $transaction = $this->createTransaction($admin, 'INV-OFFLINE', 50000);

        Livewire::actingAs($admin)
            ->test('pages::dashboard.admin.penjualan.index')
            ->call('sendWhatsApp', $transaction->id)
            ->assertSee('WhatsApp belum terhubung. Hubungkan ulang melalui Pengaturan WhatsApp.');

        Http::assertNothingSent();
    }

    public function test_meta_error_is_sanitized_and_invalid_token_requires_reconnect(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $transaction = $this->createTransaction($admin, 'INV-FAILED', 50000);
        $integration = $this->createConnectedIntegration($admin);

        Http::fake([
            'https://graph.facebook.com/v25.0/222222/messages' => Http::response([
                'error' => ['code' => 190, 'message' => 'Sensitive Meta detail'],
            ], 401),
        ]);

        Livewire::actingAs($admin)
            ->test('pages::dashboard.admin.penjualan.index')
            ->call('sendWhatsApp', $transaction->id)
            ->assertSee('Pengiriman transaksi melalui WhatsApp gagal. Kode Meta: 190.');

        $this->assertSame(
            WhatsAppIntegration::STATUS_NEEDS_RECONNECT,
            $integration->refresh()->status,
        );
    }

    public function test_sales_table_contains_per_row_whatsapp_contract(): void
    {
        $view = file_get_contents(resource_path('views/pages/dashboard/admin/penjualan/⚡index.blade.php'));

        $this->assertIsString($view);
        $this->assertStringContainsString('>WhatsApp</th>', $view);
        $this->assertStringContainsString('wire:click="sendWhatsApp({{ $transaction->id }})"', $view);
        $this->assertStringContainsString('wire:loading.attr="disabled"', $view);
        $this->assertStringContainsString('colspan="14"', $view);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function authorizedActors(): array
    {
        return [
            'admin' => ['admin', false],
            'cashier' => ['kasir_gym', false],
            'head coach' => ['pt', true],
        ];
    }

    private function createTransaction(
        User $admin,
        string $invoiceNumber,
        int $amount,
    ): MembershipTransaction {
        $member = User::factory()->create([
            'role' => 'member',
            'name' => 'Member '.$invoiceNumber,
        ]);

        return MembershipTransaction::query()->create([
            'invoice_number' => $invoiceNumber,
            'membership_id' => null,
            'user_id' => $member->id,
            'admin_id' => $admin->id,
            'shift' => 'Pagi',
            'transaction_type' => 'Pemasukan Lain',
            'package_name' => 'Paket '.$invoiceNumber,
            'amount' => $amount,
            'payment_method' => 'qris',
            'payment_date' => '2026-08-23',
            'notes' => 'Catatan '.$invoiceNumber,
        ]);
    }

    private function createConnectedIntegration(User $admin): WhatsAppIntegration
    {
        return WhatsAppIntegration::query()->create([
            'waba_id' => '111111',
            'phone_number_id' => '222222',
            'display_phone_number' => '+62 812 0000 0000',
            'verified_name' => 'JIM',
            'access_token' => 'business-access-token',
            'status' => WhatsAppIntegration::STATUS_CONNECTED,
            'connected_by_user_id' => $admin->id,
            'connected_at' => now(),
            'last_verified_at' => now(),
        ]);
    }
}
