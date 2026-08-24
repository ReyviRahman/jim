<?php

namespace Tests\Feature;

use App\Models\Membership;
use App\Models\MembershipTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
    }

    #[DataProvider('validPhoneNumbers')]
    public function test_it_normalizes_supported_indonesian_phone_numbers(string $phoneNumber, string $expectedNumber): void
    {
        $admin = $this->createActor('admin');
        $payer = $this->createPayer('Pembayar Normalisasi', $phoneNumber);
        $this->createTransaction($admin, $payer);

        $component = Livewire::actingAs($admin)
            ->test('pages::dashboard.admin.penjualan.index');

        $links = $this->extractWhatsAppLinks($component->html());

        $this->assertCount(1, $links);
        $this->assertStringStartsWith("https://wa.me/{$expectedNumber}?text=", $links[0]);
        Http::assertNothingSent();
    }

    public function test_membership_link_contains_only_the_approved_payment_summary(): void
    {
        $admin = $this->createActor('admin', 'Kasir Internal Rahasia');
        $payer = $this->createPayer('Budi Santoso', '081234567890');
        $membership = $this->createMembership($admin, $payer);
        $transaction = $this->createTransaction($admin, $payer, [
            'invoice_number' => 'INV-RAHASIA-991',
            'membership_id' => $membership->id,
            'transaction_type' => 'Membership Baru',
            'package_name' => 'Gold Couple 6 Bulan',
            'amount' => 1250000,
            'payment_method' => 'transfer',
            'start_date' => today()->addDay(),
            'end_date' => today()->addMonths(6),
            'notes' => 'Catatan internal tidak boleh terkirim',
        ]);

        $message = $this->extractWhatsAppMessages(
            Livewire::actingAs($admin)->test('pages::dashboard.admin.penjualan.index')->html(),
        )[0];

        $expectedMessage = implode("\n", [
            'Halo Budi Santoso,',
            '',
            'Terima kasih, pembayaran Anda telah kami terima.',
            '',
            'Detail pembayaran:',
            'Paket: Gold Couple 6 Bulan',
            'Tanggal pembayaran: '.$transaction->payment_date->locale('id')->isoFormat('D MMMM YYYY'),
            'Nominal: Rp 1.250.000',
            'Metode pembayaran: TRANSFER',
            'Masa aktif: '.$transaction->start_date->locale('id')->isoFormat('D MMMM YYYY').' s.d. '.$transaction->end_date->locale('id')->isoFormat('D MMMM YYYY'),
            '',
            'Terima kasih.',
        ]);

        $this->assertSame($expectedMessage, $message);
        $this->assertStringNotContainsString($transaction->invoice_number, $message);
        $this->assertStringNotContainsString('Catatan internal', $message);
        $this->assertStringNotContainsString('Kasir Internal Rahasia', $message);
        Http::assertNothingSent();
    }

    public function test_couple_or_group_membership_links_only_to_the_payer(): void
    {
        $admin = $this->createActor('admin');
        $payer = $this->createPayer('Pembayar Utama', '081211112222');
        $additionalMember = $this->createPayer('Anggota Tambahan', '081233334444');
        $membership = $this->createMembership($admin, $payer);
        $membership->members()->attach([$payer->id, $additionalMember->id]);
        $this->createTransaction($admin, $payer, [
            'membership_id' => $membership->id,
            'transaction_type' => 'Membership Couple',
        ]);

        $html = Livewire::actingAs($admin)
            ->test('pages::dashboard.admin.penjualan.index')
            ->html();
        $links = $this->extractWhatsAppLinks($html);
        $messages = $this->extractWhatsAppMessages($html);

        $this->assertCount(1, $links);
        $this->assertStringStartsWith('https://wa.me/6281211112222?', $links[0]);
        $this->assertStringNotContainsString('6281233334444', $links[0]);
        $this->assertStringContainsString('Halo Pembayar Utama,', $messages[0]);
        $this->assertStringNotContainsString('Anggota Tambahan', $messages[0]);
        Http::assertNothingSent();
    }

    public function test_other_income_uses_category_and_omits_active_period(): void
    {
        $admin = $this->createActor('admin');
        $payer = $this->createPayer('Pelanggan Harian', '+62 812-5555-6666');
        $transaction = $this->createTransaction($admin, $payer, [
            'package_name' => 'Sewa Locker',
            'amount' => 75000,
            'payment_method' => 'cash',
        ]);

        $message = $this->extractWhatsAppMessages(
            Livewire::actingAs($admin)->test('pages::dashboard.admin.penjualan.index')->html(),
        )[0];

        $this->assertStringContainsString('Kategori: Sewa Locker', $message);
        $this->assertStringContainsString('Tanggal pembayaran: '.$transaction->payment_date->locale('id')->isoFormat('D MMMM YYYY'), $message);
        $this->assertStringContainsString('Nominal: Rp 75.000', $message);
        $this->assertStringContainsString('Metode pembayaran: CASH', $message);
        $this->assertStringNotContainsString('Paket:', $message);
        $this->assertStringNotContainsString('Masa aktif:', $message);
        Http::assertNothingSent();
    }

    public function test_membership_without_active_dates_says_not_active_yet(): void
    {
        $admin = $this->createActor('admin');
        $payer = $this->createPayer('Member Belum Aktif', '81277778888');
        $membership = $this->createMembership($admin, $payer);
        $this->createTransaction($admin, $payer, [
            'membership_id' => $membership->id,
            'transaction_type' => 'Membership Baru',
            'start_date' => null,
            'end_date' => null,
        ]);

        $message = $this->extractWhatsAppMessages(
            Livewire::actingAs($admin)->test('pages::dashboard.admin.penjualan.index')->html(),
        )[0];

        $this->assertStringContainsString('Masa aktif: Belum aktif', $message);
        $this->assertStringNotContainsString(' s.d. ', $message);
    }

    #[DataProvider('invalidPhoneNumbers')]
    public function test_invalid_phone_numbers_do_not_render_wa_me_links(string $phoneNumber): void
    {
        $admin = $this->createActor('admin');
        $payer = $this->createPayer('Nomor Tidak Valid', $phoneNumber);
        $this->createTransaction($admin, $payer);

        $component = Livewire::actingAs($admin)
            ->test('pages::dashboard.admin.penjualan.index')
            ->assertSee('Tidak tersedia');

        $this->assertSame([], $this->extractWhatsAppLinks($component->html()));
        $this->assertStringNotContainsString('wa.me/', $component->html());
        Http::assertNothingSent();
    }

    #[DataProvider('authorizedActors')]
    public function test_existing_sales_roles_can_render_the_wa_me_link(string $role, bool $isHeadCoach): void
    {
        $actor = $isHeadCoach
            ? User::factory()->headCoach()->create(['shift' => 'Pagi'])
            : $this->createActor($role);
        $payer = $this->createPayer('Pembayar Role', '081299998888');
        $this->createTransaction($actor, $payer);

        $links = $this->extractWhatsAppLinks(
            Livewire::actingAs($actor)->test('pages::dashboard.admin.penjualan.index')->html(),
        );

        $this->assertCount(1, $links);
        $this->assertStringStartsWith('https://wa.me/6281299998888?', $links[0]);

        Http::assertNothingSent();
    }

    public function test_sales_link_has_new_tab_security_and_no_meta_action_contract(): void
    {
        $admin = $this->createActor('admin');
        $payer = $this->createPayer('Pembayar Aman', '081288887777');
        $transaction = $this->createTransaction($admin, $payer);

        Livewire::actingAs($admin)
            ->test('pages::dashboard.admin.penjualan.index')
            ->assertSeeHtml('target="_blank"')
            ->assertSeeHtml('rel="noopener noreferrer"')
            ->assertSeeHtml('title="Kirim pesan WhatsApp ke Pembayar Aman"')
            ->assertSeeHtml('aria-label="Kirim pesan WhatsApp ke Pembayar Aman"')
            ->assertSeeHtml('data-testid="sales-whatsapp-link-'.$transaction->id.'"');

        $view = file_get_contents(resource_path('views/pages/dashboard/admin/penjualan/⚡index.blade.php'));

        $this->assertIsString($view);
        $this->assertStringContainsString('>WhatsApp</th>', $view);
        $this->assertStringContainsString('colspan="14"', $view);
        $this->assertStringNotContainsString('sendWhatsApp', $view);
        $this->assertStringNotContainsString('whatsAppConnected', $view);
        $this->assertStringNotContainsString('WhatsApp belum terhubung', $view);

        Http::assertNothingSent();
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function validPhoneNumbers(): array
    {
        return [
            'local with zero' => ['081234567890', '6281234567890'],
            'local without zero' => ['81234567890', '6281234567890'],
            'international digits' => ['6281234567890', '6281234567890'],
            'formatted international' => ['+62 812-3456-7890', '6281234567890'],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidPhoneNumbers(): array
    {
        return [
            'too short' => ['08123'],
            'landline prefix' => ['02123456789'],
            'non Indonesian' => ['+1 202-555-0182'],
        ];
    }

    /**
     * @return array<string, array{string, bool}>
     */
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

    private function createMembership(User $admin, User $payer): Membership
    {
        return Membership::query()->create([
            'user_id' => $payer->id,
            'admin_id' => $admin->id,
            'type' => 'membership',
            'base_price' => 1250000,
            'discount_applied' => 0,
            'price_paid' => 1250000,
            'total_paid' => 1250000,
            'payment_status' => 'paid',
            'start_date' => today(),
            'membership_end_date' => today()->addMonths(6),
            'status' => 'active',
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createTransaction(
        User $admin,
        User $payer,
        array $attributes = [],
    ): MembershipTransaction {
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
            'payment_date' => today(),
            'notes' => 'Marker internal',
        ], $attributes));
    }

    /**
     * @return list<string>
     */
    private function extractWhatsAppLinks(string $html): array
    {
        $decodedHtml = html_entity_decode($html, ENT_QUOTES | ENT_HTML5);
        preg_match_all(
            '/href="(https:\/\/wa\.me\/[^\"]+)"\s+data-testid="sales-whatsapp-link-\d+"/',
            $decodedHtml,
            $matches,
        );

        return $matches[1];
    }

    /**
     * @return list<string>
     */
    private function extractWhatsAppMessages(string $html): array
    {
        return array_map(function (string $url): string {
            parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

            return (string) ($query['text'] ?? '');
        }, $this->extractWhatsAppLinks($html));
    }
}
