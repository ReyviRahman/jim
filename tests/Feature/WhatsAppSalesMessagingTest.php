<?php

namespace Tests\Feature;

use App\Models\GymPackage;
use App\Models\Membership;
use App\Models\MembershipTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Uri;
use Livewire\Features\SupportTesting\Testable;
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
    }

    #[DataProvider('validPhoneNumbers')]
    public function test_private_invoice_link_normalizes_supported_indonesian_phone_numbers(string $phoneNumber, string $expectedNumber): void
    {
        $admin = $this->createActor('admin');
        $payer = $this->createPayer('Pembayar Normalisasi', $phoneNumber);
        $transaction = $this->createTransaction($admin, $payer);

        $html = $this->salesComponent($admin)->html();

        $invoiceWhatsAppUrl = $this->extractLinkAttribute(
            $html,
            'sales-invoice-link-'.$transaction->id,
            'data-sales-invoice-whatsapp-url',
        );

        $this->assertNotNull($invoiceWhatsAppUrl);
        $this->assertStringStartsWith("https://wa.me/{$expectedNumber}?text=", $invoiceWhatsAppUrl);
        $this->assertStringStartsWith('https://wa.me/?text=', (string) $this->extractLinkAttribute(
            $html,
            'sales-whatsapp-link-'.$transaction->id,
            'href',
        ));
        Http::assertNothingSent();
    }

    public function test_membership_group_message_matches_the_follow_up_template_and_lists_every_member(): void
    {
        $admin = $this->createActor('admin');
        $followUpOne = $this->createActor('sales', 'Ratna');
        $followUpTwo = $this->createActor('kasir_gym', 'Olga');
        $payer = $this->createPayer('Vini Oktavia Marpaung', '085212750259');
        $alphabeticallyFirstMember = $this->createPayer('Alya Putri', '081222223333');
        $alphabeticallyLastMember = $this->createPayer('Zeta Putri', '081244445555');
        $membership = $this->createMembership($admin, $payer, [
            'payment_status' => 'paid',
            'status' => 'active',
            'is_active' => true,
        ]);
        $membership->members()->attach([
            $payer->id,
            $alphabeticallyLastMember->id,
            $alphabeticallyFirstMember->id,
        ]);
        $transaction = $this->createTransaction($admin, $payer, [
            'membership_id' => $membership->id,
            'follow_up_id' => $followUpOne->id,
            'follow_up_id_two' => $followUpTwo->id,
            'transaction_type' => 'Renew Membership',
            'package_name' => '3 Bulan',
            'amount' => 450000,
            'payment_method' => 'transfer',
            'payment_date' => '2026-08-22',
            'start_date' => '2026-09-07',
            'end_date' => '2026-12-07',
            'notes' => 'SUDAH TRANSFER',
        ]);

        $message = $this->extractGroupMessage(
            $this->salesComponent($admin)->html(),
            $transaction,
        );

        $this->assertSame(implode("\n", [
            'ASSALAMUALAIKUM WR.WB.',
            '',
            '- FOLLOW UP 1 : RATNA',
            '- FOLLOW UP 2 : OLGA',
            '- STATUS : RENEW MEMBERSHIP',
            '- MASA AKTIF : 3 BULAN (07/09/2026 - 07/12/2026)',
            '',
            '1. VINI OKTAVIA MARPAUNG',
            '   NO WA : 085212750259',
            '2. ALYA PUTRI',
            '   NO WA : 081222223333',
            '3. ZETA PUTRI',
            '   NO WA : 081244445555',
            '',
            '- JENIS LAYANAN : MEMBERSHIP',
            '',
            '- TOTAL PEMBAYARAN :',
            '',
            '- Rp450.000 (TF BCA)',
            '',
            '- WAKTU KUNJUNGAN : SABTU, 22 AGUSTUS 2026',
            '',
            '- STATUS : LUNAS',
            '',
            '- NOTE :',
            '',
            'SUDAH TRANSFER',
            '',
            'SUDAH DIAKTIFKAN DISISTEM',
        ]), $message);
        Http::assertNothingSent();
    }

    public function test_follow_up_one_and_two_are_both_rendered_when_they_are_the_same_person(): void
    {
        $admin = $this->createActor('admin');
        $followUp = $this->createActor('sales', 'Ratna Bersama');
        $payer = $this->createPayer('Member Follow Up', '081255556666');
        $membership = $this->createMembership($admin, $payer);
        $transaction = $this->createTransaction($admin, $payer, [
            'membership_id' => $membership->id,
            'follow_up_id' => $followUp->id,
            'follow_up_id_two' => $followUp->id,
        ]);

        $message = $this->extractGroupMessage(
            $this->salesComponent($admin)->html(),
            $transaction,
        );

        $this->assertStringContainsString('- FOLLOW UP 1 : RATNA BERSAMA', $message);
        $this->assertStringContainsString('- FOLLOW UP 2 : RATNA BERSAMA', $message);
    }

    public function test_share_url_preserves_newlines_symbols_and_utf8_text(): void
    {
        $admin = $this->createActor('admin');
        $payer = $this->createPayer('Member Encoding', '081255550000');
        $transaction = $this->createTransaction($admin, $payer, [
            'notes' => 'Pembayaran valid & siap – terima kasih',
        ]);

        $shareUrl = $this->extractLinkAttribute(
            $this->salesComponent($admin)->html(),
            'sales-whatsapp-link-'.$transaction->id,
            'href',
        );

        $this->assertNotNull($shareUrl);
        $this->assertStringStartsWith('https://wa.me/?text=', $shareUrl);
        $this->assertStringContainsString('%0A', $shareUrl);
        $this->assertStringContainsString('%26', $shareUrl);
        $this->assertStringContainsString('%E2%80%93', $shareUrl);
        $this->assertStringContainsString(
            'Pembayaran valid & siap – terima kasih',
            (string) Uri::of($shareUrl)->query()->get('text'),
        );
    }

    public function test_group_message_includes_payment_proof_link_only_when_available(): void
    {
        $admin = $this->createActor('admin');
        $payer = $this->createPayer('Member Bukti Pembayaran', '081255551111');
        $paymentProofPath = 'membership-payment-proofs/2026/08/bukti.webp';
        $transactionWithProof = $this->createTransaction($admin, $payer, [
            'payment_proof_path' => $paymentProofPath,
        ]);
        $transactionWithoutProof = $this->createTransaction($admin, $payer);

        $html = $this->salesComponent($admin)->html();
        $messageWithProof = $this->extractGroupMessage($html, $transactionWithProof);
        $messageWithoutProof = $this->extractGroupMessage($html, $transactionWithoutProof);

        $this->assertStringContainsString(
            '- BUKTI PEMBAYARAN : '.asset('storage/'.$paymentProofPath),
            $messageWithProof,
        );
        $this->assertStringNotContainsString('- BUKTI PEMBAYARAN :', $messageWithoutProof);
        $this->assertStringNotContainsString(asset('storage/'.$paymentProofPath), $messageWithoutProof);
    }

    #[DataProvider('serviceTypes')]
    public function test_group_message_maps_each_membership_service_type(string $membershipType, string $expectedLabel): void
    {
        $admin = $this->createActor('admin');
        $payer = $this->createPayer('Member Layanan', '081266667777');
        $membership = $this->createMembership($admin, $payer, ['type' => $membershipType]);
        $transaction = $this->createTransaction($admin, $payer, ['membership_id' => $membership->id]);

        $message = $this->extractGroupMessage(
            $this->salesComponent($admin)->html(),
            $transaction,
        );

        $this->assertStringContainsString('- JENIS LAYANAN : '.$expectedLabel, $message);
    }

    #[DataProvider('ptMembershipCategories')]
    public function test_pt_service_label_includes_membership_category_and_total_sessions(
        string $category,
        int $totalSessions,
        string $expectedLabel,
    ): void {
        $admin = $this->createActor('admin');
        $payer = $this->createPayer('Member PT '.$category, '081266660000');
        $ptPackage = GymPackage::query()->create([
            'type' => 'pt',
            'name' => 'Paket PT '.ucfirst($category),
            'category' => $category,
            'max_members' => $category === 'couple' ? 2 : 1,
            'pt_sessions' => 10,
            'price' => 500000,
            'discount' => 0,
            'is_active' => true,
        ]);
        $membership = $this->createMembership($admin, $payer, [
            'type' => 'pt',
            'pt_package_id' => $ptPackage->id,
            'total_sessions' => $totalSessions,
            'remaining_sessions' => $totalSessions,
        ]);
        $transaction = $this->createTransaction($admin, $payer, ['membership_id' => $membership->id]);

        $message = $this->extractGroupMessage(
            $this->salesComponent($admin)->html(),
            $transaction,
        );

        $this->assertStringContainsString('- JENIS LAYANAN : '.$expectedLabel, $message);
    }

    #[DataProvider('gymMembershipCategories')]
    public function test_membership_service_label_includes_category_without_a_session_suffix(
        string $category,
        string $expectedLabel,
    ): void {
        $admin = $this->createActor('admin');
        $payer = $this->createPayer('Member Gym '.$category, '081266661111');
        $gymPackage = GymPackage::query()->create([
            'type' => 'gym',
            'name' => 'Paket Gym '.ucfirst($category),
            'category' => $category,
            'max_members' => $category === 'couple' ? 2 : 1,
            'pt_sessions' => null,
            'price' => 300000,
            'discount' => 0,
            'is_active' => true,
        ]);
        $membership = $this->createMembership($admin, $payer, [
            'type' => 'membership',
            'gym_package_id' => $gymPackage->id,
            'total_sessions' => null,
            'remaining_sessions' => null,
        ]);
        $transaction = $this->createTransaction($admin, $payer, ['membership_id' => $membership->id]);

        $message = $this->extractGroupMessage(
            $this->salesComponent($admin)->html(),
            $transaction,
        );

        $this->assertStringContainsString(
            "- JENIS LAYANAN : {$expectedLabel}\n\n- TOTAL PEMBAYARAN :",
            $message,
        );
    }

    #[DataProvider('membershipPaymentStatuses')]
    public function test_group_message_uses_current_payment_and_activation_status(
        string $paymentStatus,
        string $membershipStatus,
        bool $isActive,
        string $expectedPaymentLabel,
        string $expectedActivationLabel,
    ): void {
        $admin = $this->createActor('admin');
        $payer = $this->createPayer('Member Status', '081277778888');
        $membership = $this->createMembership($admin, $payer, [
            'payment_status' => $paymentStatus,
            'status' => $membershipStatus,
            'is_active' => $isActive,
        ]);
        $transaction = $this->createTransaction($admin, $payer, ['membership_id' => $membership->id]);

        $message = $this->extractGroupMessage(
            $this->salesComponent($admin)->html(),
            $transaction,
        );

        $this->assertStringContainsString('- STATUS : '.$expectedPaymentLabel, $message);
        $this->assertStringEndsWith($expectedActivationLabel, $message);
    }

    public function test_other_income_uses_payer_and_fallback_values_without_membership_activation_line(): void
    {
        $admin = $this->createActor('admin');
        $payer = $this->createPayer('Pelanggan Harian', '02123456789');
        $transaction = $this->createTransaction($admin, $payer, [
            'package_name' => 'Sewa Locker',
            'amount' => 75000,
            'payment_method' => 'cash',
            'payment_date' => '2026-08-22',
            'notes' => null,
        ]);

        $html = $this->salesComponent($admin)->html();
        $message = $this->extractGroupMessage($html, $transaction);

        $this->assertStringStartsWith('https://wa.me/?text=', (string) $this->extractLinkAttribute(
            $html,
            'sales-whatsapp-link-'.$transaction->id,
            'href',
        ));
        $this->assertNull($this->extractLinkAttribute(
            $html,
            'sales-invoice-link-'.$transaction->id,
            'data-sales-invoice-whatsapp-url',
        ));
        $this->assertStringContainsString('- FOLLOW UP 1 : -', $message);
        $this->assertStringContainsString('- FOLLOW UP 2 : -', $message);
        $this->assertStringContainsString('- MASA AKTIF : BELUM AKTIF', $message);
        $this->assertStringContainsString('1. PELANGGAN HARIAN', $message);
        $this->assertStringContainsString('- JENIS LAYANAN : PEMASUKAN LAIN', $message);
        $this->assertStringContainsString('- Rp75.000 (CASH)', $message);
        $this->assertStringContainsString("- NOTE :\n\n-", $message);
        $this->assertStringNotContainsString('DIAKTIFKAN DISISTEM', $message);
        Http::assertNothingSent();
    }

    public function test_membership_without_active_dates_says_not_active_yet(): void
    {
        $admin = $this->createActor('admin');
        $payer = $this->createPayer('Member Belum Aktif', '081288889999');
        $membership = $this->createMembership($admin, $payer);
        $transaction = $this->createTransaction($admin, $payer, [
            'membership_id' => $membership->id,
            'start_date' => null,
            'end_date' => null,
        ]);

        $message = $this->extractGroupMessage(
            $this->salesComponent($admin)->html(),
            $transaction,
        );

        $this->assertStringContainsString('- MASA AKTIF : BELUM AKTIF', $message);
        $this->assertStringNotContainsString('BELUM AKTIF (', $message);
    }

    public function test_split_payment_messages_remain_scoped_to_the_clicked_transaction_row(): void
    {
        $admin = $this->createActor('admin');
        $payer = $this->createPayer('Member Split', '081299990000');
        $membership = $this->createMembership($admin, $payer);
        $transferTransaction = $this->createTransaction($admin, $payer, [
            'membership_id' => $membership->id,
            'invoice_number' => 'INV-SPLIT-TRANSFER',
            'amount' => 300000,
            'payment_method' => 'transfer',
        ]);
        $qrisTransaction = $this->createTransaction($admin, $payer, [
            'membership_id' => $membership->id,
            'invoice_number' => 'INV-SPLIT-QRIS',
            'amount' => 150000,
            'payment_method' => 'qris',
        ]);

        $html = $this->salesComponent($admin)->html();
        $transferMessage = $this->extractGroupMessage($html, $transferTransaction);
        $qrisMessage = $this->extractGroupMessage($html, $qrisTransaction);

        $this->assertStringContainsString('- Rp300.000 (TF BCA)', $transferMessage);
        $this->assertStringNotContainsString('Rp150.000', $transferMessage);
        $this->assertStringContainsString('- Rp150.000 (QRIS)', $qrisMessage);
        $this->assertStringNotContainsString('Rp300.000', $qrisMessage);
    }

    public function test_share_link_does_not_depend_on_group_configuration_or_a_valid_payer_phone(): void
    {
        $admin = $this->createActor('admin');
        $payer = $this->createPayer('Member Tanpa Nomor Valid', '02123456789');
        $transaction = $this->createTransaction($admin, $payer);

        $component = $this->salesComponent($admin);

        $this->assertStringStartsWith('https://wa.me/?text=', (string) $this->extractLinkAttribute(
            $component->html(),
            'sales-whatsapp-link-'.$transaction->id,
            'href',
        ));
        $this->assertNull($this->extractLinkAttribute(
            $component->html(),
            'sales-invoice-link-'.$transaction->id,
            'data-sales-invoice-whatsapp-url',
        ));
    }

    #[DataProvider('authorizedActors')]
    public function test_existing_sales_roles_can_open_the_prefilled_whatsapp_picker(string $role, bool $isHeadCoach): void
    {
        $actor = $isHeadCoach
            ? User::factory()->headCoach()->create(['shift' => 'Pagi'])
            : $this->createActor($role);
        $payer = $this->createPayer('Pembayar Role', '081299998888');
        $transaction = $this->createTransaction($actor, $payer);

        $html = $this->salesComponent($actor)
            ->assertSeeHtml('target="_blank"')
            ->assertSeeHtml('rel="noopener noreferrer"')
            ->assertSeeHtml('title="Buka WhatsApp dengan pesan siap, lalu pilih grup"')
            ->assertSeeHtml('aria-label="Buka WhatsApp dengan pesan siap untuk Pembayar Role, lalu pilih grup"')
            ->html();

        $this->assertStringStartsWith('https://wa.me/?text=', (string) $this->extractLinkAttribute(
            $html,
            'sales-whatsapp-link-'.$transaction->id,
            'href',
        ));
        Http::assertNothingSent();
    }

    public function test_each_invoice_download_link_keeps_its_members_private_whatsapp_url(): void
    {
        $admin = $this->createActor('admin');
        $firstPayer = $this->createPayer('Member Invoice Pertama', '081211112222');
        $secondPayer = $this->createPayer('Member Invoice Kedua', '081233334444');
        $firstTransaction = $this->createTransaction($admin, $firstPayer);
        $secondTransaction = $this->createTransaction($admin, $secondPayer);

        $html = $this->salesComponent($admin)->html();

        $this->assertStringStartsWith('https://wa.me/?text=', (string) $this->extractLinkAttribute(
            $html,
            'sales-whatsapp-link-'.$firstTransaction->id,
            'href',
        ));
        $this->assertStringStartsWith('https://wa.me/?text=', (string) $this->extractLinkAttribute(
            $html,
            'sales-whatsapp-link-'.$secondTransaction->id,
            'href',
        ));
        $this->assertStringStartsWith('https://wa.me/6281211112222?', (string) $this->extractLinkAttribute(
            $html,
            'sales-invoice-link-'.$firstTransaction->id,
            'data-sales-invoice-whatsapp-url',
        ));
        $this->assertStringStartsWith('https://wa.me/6281233334444?', (string) $this->extractLinkAttribute(
            $html,
            'sales-invoice-link-'.$secondTransaction->id,
            'data-sales-invoice-whatsapp-url',
        ));
        Http::assertNothingSent();
    }

    public function test_group_share_needs_no_browser_handler_and_invoice_whatsapp_remains_private(): void
    {
        $javascript = file_get_contents(resource_path('js/app.js'));

        $this->assertIsString($javascript);
        $this->assertStringNotContainsString('data-sales-whatsapp-group-message', $javascript);
        $this->assertStringNotContainsString('navigator.clipboard?.writeText', $javascript);
        $this->assertStringNotContainsString("document.execCommand('copy')", $javascript);
        $this->assertStringNotContainsString('openSalesWhatsAppGroup', $javascript);
        $this->assertStringNotContainsString('chat.whatsapp.com', $javascript);
        $this->assertStringContainsString("closest('a[data-sales-invoice-whatsapp-url]')", $javascript);
        $this->assertStringContainsString("whatsAppUrl.hostname !== 'wa.me'", $javascript);
        $this->assertStringContainsString("window.open(whatsAppUrl.toString(), '_blank', 'noopener,noreferrer')", $javascript);
        $this->assertStringContainsString("document.addEventListener('click', openWhatsAppAfterInvoiceDownload)", $javascript);
    }

    public function test_sales_view_keeps_icon_only_actions_and_no_meta_send_contract(): void
    {
        $view = file_get_contents(resource_path('views/pages/dashboard/admin/penjualan/⚡index.blade.php'));
        $envExample = file_get_contents(base_path('.env.example'));
        $servicesConfig = file_get_contents(config_path('services.php'));

        $this->assertIsString($view);
        $this->assertIsString($envExample);
        $this->assertIsString($servicesConfig);
        $this->assertStringContainsString('>Aksi</th>', $view);
        $this->assertStringContainsString('colspan="14"', $view);
        $this->assertStringNotContainsString('<span>Kirim WA</span>', $view);
        $this->assertStringNotContainsString('sendWhatsApp', $view);
        $this->assertStringNotContainsString('whatsAppConnected', $view);
        $this->assertStringNotContainsString('WhatsApp belum terhubung', $view);
        $this->assertStringNotContainsString('WHATSAPP_SALES_GROUP_URL', $envExample);
        $this->assertStringNotContainsString("'whatsapp_sales'", $servicesConfig);
    }

    /** @return array<string, array{string, string}> */
    public static function validPhoneNumbers(): array
    {
        return [
            'local with zero' => ['081234567890', '6281234567890'],
            'local without zero' => ['81234567890', '6281234567890'],
            'international digits' => ['6281234567890', '6281234567890'],
            'formatted international' => ['+62 812-3456-7890', '6281234567890'],
        ];
    }

    /** @return array<string, array{string, string}> */
    public static function serviceTypes(): array
    {
        return [
            'membership' => ['membership', 'MEMBERSHIP'],
            'personal training' => ['pt', 'MEMBER PT'],
            'bundle' => ['bundle_pt_membership', 'BUNDLE PT + MEMBERSHIP'],
            'visit' => ['visit', 'VISIT'],
        ];
    }

    /** @return array<string, array{string, int, string}> */
    public static function ptMembershipCategories(): array
    {
        return [
            'single five sessions' => ['single', 5, 'MEMBER PT SINGLE 5 SESI'],
            'couple five sessions' => ['couple', 5, 'MEMBER PT COUPLE 5 SESI'],
        ];
    }

    /** @return array<string, array{string, string}> */
    public static function gymMembershipCategories(): array
    {
        return [
            'single' => ['single', 'MEMBERSHIP SINGLE'],
            'couple' => ['couple', 'MEMBERSHIP COUPLE'],
        ];
    }

    /** @return array<string, array{string, string, bool, string, string}> */
    public static function membershipPaymentStatuses(): array
    {
        return [
            'paid and active' => ['paid', 'active', true, 'LUNAS', 'SUDAH DIAKTIFKAN DISISTEM'],
            'partial and pending' => ['partial', 'pending', false, 'CICILAN', 'BELUM DIAKTIFKAN DISISTEM'],
            'unpaid and pending' => ['unpaid', 'pending', false, 'BELUM LUNAS', 'BELUM DIAKTIFKAN DISISTEM'],
            'paid but completed' => ['paid', 'completed', false, 'LUNAS', 'BELUM DIAKTIFKAN DISISTEM'],
        ];
    }

    /** @return array<string, array{string, bool}> */
    public static function authorizedActors(): array
    {
        return [
            'admin' => ['admin', false],
            'cashier' => ['kasir_gym', false],
            'head coach' => ['pt', true],
        ];
    }

    private function createActor(string $role, ?string $name = null): User
    {
        return User::factory()->create([
            'role' => $role,
            'name' => $name ?? ucfirst($role).' Penjualan',
            'shift' => 'Pagi',
        ]);
    }

    private function createPayer(string $name, string $phoneNumber): User
    {
        return User::factory()->create([
            'role' => 'member',
            'name' => $name,
            'phone' => $phoneNumber,
        ]);
    }

    /** @param array<string, mixed> $attributes */
    private function createMembership(User $admin, User $payer, array $attributes = []): Membership
    {
        return Membership::query()->create(array_merge([
            'user_id' => $payer->id,
            'admin_id' => $admin->id,
            'type' => 'membership',
            'base_price' => 1250000,
            'discount_applied' => 0,
            'price_paid' => 1250000,
            'total_paid' => 1250000,
            'payment_status' => 'paid',
            'start_date' => '2026-09-07',
            'membership_end_date' => '2026-12-07',
            'status' => 'active',
            'is_active' => true,
        ], $attributes));
    }

    /** @param array<string, mixed> $attributes */
    private function createTransaction(User $admin, User $payer, array $attributes = []): MembershipTransaction
    {
        return MembershipTransaction::query()->create(array_merge([
            'invoice_number' => fake()->unique()->bothify('INV-WA-########'),
            'membership_id' => null,
            'user_id' => $payer->id,
            'admin_id' => $admin->id,
            'shift' => 'Pagi',
            'transaction_type' => 'Pemasukan Lain',
            'package_name' => 'Biaya Harian',
            'amount' => 175000,
            'payment_method' => 'qris',
            'payment_date' => '2026-08-22',
            'start_date' => '2026-09-07',
            'end_date' => '2026-12-07',
            'notes' => 'Marker internal',
        ], $attributes));
    }

    private function extractGroupMessage(string $html, MembershipTransaction $transaction): string
    {
        $shareUrl = $this->extractLinkAttribute(
            $html,
            'sales-whatsapp-link-'.$transaction->id,
            'href',
        );

        $this->assertNotNull($shareUrl);

        return (string) Uri::of($shareUrl)->query()->get('text');
    }

    private function salesComponent(User $actor): Testable
    {
        return Livewire::actingAs($actor)
            ->test('pages::dashboard.admin.penjualan.index')
            ->set('filterTime', 'all');
    }

    private function extractLinkAttribute(string $html, string $testId, string $attribute): ?string
    {
        $decodedHtml = html_entity_decode($html, ENT_QUOTES | ENT_HTML5);
        $testIdPattern = preg_quote($testId, '/');
        $attributePattern = preg_quote($attribute, '/');
        preg_match(
            '/<a\b(?=[^>]*data-testid="'.$testIdPattern.'")(?=[^>]*'.$attributePattern.'="([^"]*)")[^>]*>/s',
            $decodedHtml,
            $matches,
        );

        return $matches[1] ?? null;
    }
}
