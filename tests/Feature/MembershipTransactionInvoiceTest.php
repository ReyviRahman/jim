<?php

namespace Tests\Feature;

use App\Actions\BuildMembershipTransactionInvoiceData;
use App\Models\Membership;
use App\Models\MembershipTransaction;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as PdfDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MembershipTransactionInvoiceTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('authorizedActors')]
    public function test_authorized_sales_roles_can_download_a_transaction_invoice(string $actorType): void
    {
        $actor = $this->createActor($actorType);
        $payer = $this->createUser('member');
        $transaction = $this->createTransaction($actor, $payer, null, [
            'invoice_number' => 'INV-TXN-DOWNLOAD-'.$actorType,
        ]);

        $response = $this->actingAs($actor)->get(route('admin.penjualan.invoice', $transaction));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $response->assertHeader(
            'content-disposition',
            'attachment; filename=Invoice_Transaksi_'.Str::slug($transaction->invoice_number, '_').'.pdf',
        );
        $response->assertHeader('cache-control', 'max-age=0, no-store, private');
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_download_uses_the_clicked_transaction_snapshot_instead_of_the_latest_membership_payment(): void
    {
        $admin = $this->createActor('admin');
        $payer = $this->createUser('member');
        $membership = $this->createMembership($admin, $payer);
        $clickedTransaction = $this->createTransaction($admin, $payer, $membership, [
            'invoice_number' => 'INV-CLICKED-ROW',
            'transaction_type' => 'Cicilan Pertama',
            'package_name' => 'Snapshot Paket Lama',
            'amount' => 125000,
            'payment_method' => 'qris',
            'payment_date' => '2026-08-01',
            'start_date' => '2026-08-02',
            'end_date' => '2026-09-02',
        ]);
        $this->createTransaction($admin, $payer, $membership, [
            'invoice_number' => 'INV-LATEST-ROW',
            'amount' => 999999,
            'payment_method' => 'transfer',
            'payment_date' => '2026-08-20',
        ]);

        $pdf = Mockery::mock(PdfDocument::class);
        $pdf->shouldReceive('setPaper')->once()->with('a4')->andReturnSelf();
        $pdf->shouldReceive('download')
            ->once()
            ->with('Invoice_Transaksi_inv_clicked_row.pdf')
            ->andReturn(response('%PDF invoice', 200, ['Content-Type' => 'application/pdf']));

        Pdf::shouldReceive('loadView')
            ->once()
            ->with('pages.dashboard.admin.penjualan.invoice-pdf', Mockery::on(function (array $data): bool {
                $this->assertSame('INV-CLICKED-ROW', $data['invoiceNumber']);
                $this->assertSame('QRIS', $data['paymentMethod']);
                $this->assertSame('Snapshot Paket Lama', $data['detailName']);
                $this->assertSame('125000', $data['membershipTransaction']->amount);
                $this->assertTrue($data['isMembershipTransaction']);
                $this->assertTrue(URL::hasValidSignature(request()->create($data['verificationUrl'])));

                return true;
            }))
            ->andReturn($pdf);

        $this->actingAs($admin)
            ->get(route('admin.penjualan.invoice', $clickedTransaction))
            ->assertOk();
    }

    public function test_membership_invoice_view_contains_only_the_selected_payment_and_snapshot_period(): void
    {
        $admin = $this->createActor('admin');
        $payer = $this->createUser('member');
        $membership = $this->createMembership($admin, $payer);
        $selectedTransaction = $this->createTransaction($admin, $payer, $membership, [
            'invoice_number' => 'INV-MEMBER-SELECTED',
            'transaction_type' => 'Cicilan Membership',
            'package_name' => 'Paket Snapshot 3 Bulan',
            'amount' => 175000,
            'payment_method' => 'debit',
            'start_date' => '2026-08-05',
            'end_date' => '2026-11-05',
        ]);
        $this->createTransaction($admin, $payer, $membership, [
            'invoice_number' => 'INV-MEMBER-OTHER',
            'transaction_type' => 'Pembayaran Lain',
            'amount' => 987654,
        ]);

        $this->view(
            'pages.dashboard.admin.penjualan.invoice-pdf',
            $this->invoiceViewData($selectedTransaction),
        )
            ->assertSee('INV-MEMBER-SELECTED')
            ->assertSee('Cicilan Membership')
            ->assertSee('Paket Snapshot 3 Bulan')
            ->assertSee('05 Agustus 2026')
            ->assertSee('05 November 2026')
            ->assertSee('Rp 175.000')
            ->assertSee('DEBIT')
            ->assertDontSee('INV-MEMBER-OTHER')
            ->assertDontSee('Rp 987.654');
    }

    public function test_split_rows_generate_distinct_invoice_data_and_verification_urls(): void
    {
        $admin = $this->createActor('admin');
        $payer = $this->createUser('member');
        $membership = $this->createMembership($admin, $payer);
        $qrisTransaction = $this->createTransaction($admin, $payer, $membership, [
            'invoice_number' => 'INV-SPLIT-QRIS',
            'amount' => 100000,
            'payment_method' => 'qris',
        ]);
        $transferTransaction = $this->createTransaction($admin, $payer, $membership, [
            'invoice_number' => 'INV-SPLIT-TRANSFER',
            'amount' => 250000,
            'payment_method' => 'transfer',
        ]);

        $qrisData = $this->invoiceViewData($qrisTransaction);
        $transferData = $this->invoiceViewData($transferTransaction);

        $this->assertSame('INV-SPLIT-QRIS', $qrisData['invoiceNumber']);
        $this->assertSame('100000', $qrisData['membershipTransaction']->amount);
        $this->assertSame('QRIS', $qrisData['paymentMethod']);
        $this->assertSame('INV-SPLIT-TRANSFER', $transferData['invoiceNumber']);
        $this->assertSame('250000', $transferData['membershipTransaction']->amount);
        $this->assertSame('TRANSFER', $transferData['paymentMethod']);
        $this->assertNotSame($qrisData['verificationUrl'], $transferData['verificationUrl']);
    }

    public function test_other_income_invoice_uses_category_and_omits_membership_only_sections(): void
    {
        $admin = $this->createActor('admin');
        $payer = $this->createUser('member');
        $transaction = $this->createTransaction($admin, $payer, null, [
            'invoice_number' => 'INV-INCOME-001',
            'transaction_type' => 'Pemasukan Lain',
            'package_name' => 'Merchandise Gym',
            'amount' => 85000,
            'payment_method' => 'cash',
            'notes' => 'Pembelian kaos',
        ]);

        $this->view('pages.dashboard.admin.penjualan.invoice-pdf', $this->invoiceViewData($transaction))
            ->assertSee('Detail Pemasukan')
            ->assertSee('Kategori')
            ->assertSee('Merchandise Gym')
            ->assertSee('Rp 85.000')
            ->assertSee('Konfirmasi Pembayaran')
            ->assertDontSee('Tanggal Mulai')
            ->assertDontSee('Tanggal Berakhir')
            ->assertDontSee('Ketentuan Pembayaran');
    }

    #[DataProvider('supportedPaymentProofImages')]
    public function test_valid_payment_proof_is_embedded_on_a_second_invoice_page(
        string $extension,
        string $expectedMimeType,
    ): void {
        Storage::fake('public');

        $admin = $this->createActor('admin');
        $payer = $this->createUser('member');
        $path = 'membership-payment-proofs/2026/08/proof.'.$extension;
        $imageBytes = $this->imageBytes($extension);
        Storage::disk('public')->put($path, $imageBytes);
        $transaction = $this->createTransaction($admin, $payer, null, [
            'invoice_number' => 'INV-PROOF-'.Str::upper($extension),
            'payment_method' => 'transfer',
            'payment_proof_path' => $path,
        ]);

        $data = $this->invoiceViewData($transaction, includePaymentProof: true);

        $this->assertStringStartsWith('data:'.$expectedMimeType.';base64,', $data['paymentProofDataUri']);
        $this->assertSame(
            $imageBytes,
            base64_decode(str($data['paymentProofDataUri'])->after('base64,')->toString(), true),
        );

        $view = $this->view('pages.dashboard.admin.penjualan.invoice-pdf', $data);
        $view->assertSee('Lampiran Bukti Pembayaran')
            ->assertSee('Bukti pembayaran invoice '.$transaction->invoice_number);

        $pdf = Pdf::loadView('pages.dashboard.admin.penjualan.invoice-pdf', $data)->setPaper('a4');
        $pdf->render();

        $this->assertSame(2, $pdf->getDomPDF()->getCanvas()->get_page_count());
    }

    public function test_invoice_without_an_available_valid_proof_remains_one_page(): void
    {
        Storage::fake('public');

        $admin = $this->createActor('admin');
        $payer = $this->createUser('member');
        $invalidProofs = [
            null,
            'membership-payment-proofs/2026/08/missing.png',
            'membership-payment-proofs/2026/08/corrupt.jpg',
            'membership-payment-proofs/2026/08/disallowed.gif',
            'membership-payment-proofs/2026/08/oversized.png',
            '../private/proof.png',
        ];

        Storage::disk('public')->put('membership-payment-proofs/2026/08/corrupt.jpg', 'not an image');
        Storage::disk('public')->put('membership-payment-proofs/2026/08/disallowed.gif', base64_decode('R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw=='));
        Storage::disk('public')->put('membership-payment-proofs/2026/08/oversized.png', str_repeat('x', (10 * 1024 * 1024) + 1));

        foreach ($invalidProofs as $index => $path) {
            $transaction = $this->createTransaction($admin, $payer, null, [
                'invoice_number' => 'INV-NO-PROOF-'.$index,
                'payment_proof_path' => $path,
            ]);
            $data = $this->invoiceViewData($transaction, includePaymentProof: true);

            $this->assertNull($data['paymentProofDataUri']);
            $this->view('pages.dashboard.admin.penjualan.invoice-pdf', $data)
                ->assertDontSee('Lampiran Bukti Pembayaran');

            $pdf = Pdf::loadView('pages.dashboard.admin.penjualan.invoice-pdf', $data)->setPaper('a4');
            $pdf->render();

            $this->assertSame(1, $pdf->getDomPDF()->getCanvas()->get_page_count());
        }
    }

    public function test_public_verification_does_not_load_payment_proof_contents(): void
    {
        Storage::fake('public');

        $admin = $this->createActor('admin');
        $payer = $this->createUser('member');
        $path = 'membership-payment-proofs/2026/08/private.png';
        Storage::disk('public')->put($path, $this->imageBytes('png'));
        $transaction = $this->createTransaction($admin, $payer, null, [
            'payment_proof_path' => $path,
        ]);

        $this->assertNull($this->invoiceViewData($transaction)['paymentProofDataUri']);

        $verificationUrl = URL::signedRoute('transaction.invoice.verify', [
            'membershipTransaction' => $transaction,
        ]);

        $this->get($verificationUrl)
            ->assertOk()
            ->assertDontSee('Lampiran Bukti Pembayaran')
            ->assertDontSee('data:image/png;base64,');
    }

    public function test_guest_can_verify_a_transaction_with_a_valid_signed_url_without_internal_data(): void
    {
        $admin = $this->createActor('admin');
        $payer = $this->createUser('member', [
            'name' => 'Pembayar Verifikasi',
            'phone' => '081299991111',
            'email' => 'private-payer@example.com',
        ]);
        $transaction = $this->createTransaction($admin, $payer, null, [
            'invoice_number' => 'INV-VERIFY-001',
            'package_name' => 'Day Pass',
            'amount' => 75000,
            'payment_method' => 'qris',
            'payment_proof_path' => 'membership-payment-proofs/private-proof.webp',
            'notes' => 'INTERNAL-NOTE-DO-NOT-SHOW',
        ]);
        $verificationUrl = URL::signedRoute('transaction.invoice.verify', [
            'membershipTransaction' => $transaction,
        ]);

        $this->get($verificationUrl)
            ->assertOk()
            ->assertHeader('cache-control', 'max-age=0, no-store, private')
            ->assertSee('Invoice Terverifikasi')
            ->assertSee('INV-VERIFY-001')
            ->assertSee('Pembayar Verifikasi')
            ->assertSee('Day Pass')
            ->assertSee('Rp 75.000')
            ->assertSee('QRIS')
            ->assertDontSee('081299991111')
            ->assertDontSee('private-payer@example.com')
            ->assertDontSee('INTERNAL-NOTE-DO-NOT-SHOW')
            ->assertDontSee('private-proof.webp')
            ->assertDontSee($admin->name);
    }

    public function test_unsigned_tampered_and_missing_transaction_verification_urls_are_rejected(): void
    {
        $admin = $this->createActor('admin');
        $payer = $this->createUser('member');
        $transaction = $this->createTransaction($admin, $payer);

        $this->get(route('transaction.invoice.verify', $transaction))->assertForbidden();

        $verificationUrl = URL::signedRoute('transaction.invoice.verify', [
            'membershipTransaction' => $transaction,
        ]);
        $this->get($verificationUrl.'&tampered=1')->assertForbidden();

        $missingUrl = URL::signedRoute('transaction.invoice.verify', [
            'membershipTransaction' => 999999,
        ]);
        $this->get($missingUrl)->assertNotFound();
    }

    public function test_guest_and_member_cannot_download_transaction_invoices(): void
    {
        $admin = $this->createActor('admin');
        $payer = $this->createUser('member');
        $transaction = $this->createTransaction($admin, $payer);

        $this->get(route('admin.penjualan.invoice', $transaction))
            ->assertRedirect(route('login'));

        $this->actingAs($payer)
            ->get(route('admin.penjualan.invoice', $transaction))
            ->assertRedirect(route('home'));
    }

    public function test_sales_table_shows_an_accessible_icon_only_invoice_link_in_the_action_column(): void
    {
        $admin = $this->createActor('admin');
        $payer = $this->createUser('member', [
            'name' => 'Member Invoice WA',
            'phone' => '081288887777',
        ]);
        $transaction = $this->createTransaction($admin, $payer, null, [
            'invoice_number' => 'INV-ACTION-ICON',
        ]);

        Livewire::actingAs($admin)
            ->test('pages::dashboard.admin.penjualan.index')
            ->assertSee('Aksi')
            ->assertSeeHtml('href="'.route('admin.penjualan.invoice', $transaction).'"')
            ->assertSeeHtml('data-testid="sales-invoice-link-'.$transaction->id.'"')
            ->assertSeeHtml('data-sales-invoice-whatsapp-url="https://wa.me/6281288887777?text=')
            ->assertSeeHtml('title="Unduh invoice INV-ACTION-ICON dan buka WhatsApp ke Member Invoice WA di tab baru"')
            ->assertSeeHtml('aria-label="Unduh invoice INV-ACTION-ICON dan buka WhatsApp ke Member Invoice WA di tab baru"')
            ->assertDontSeeHtml('<span>Unduh invoice</span>');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function authorizedActors(): array
    {
        return [
            'admin' => ['admin'],
            'cashier' => ['kasir_gym'],
            'head coach' => ['head_coach'],
        ];
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function supportedPaymentProofImages(): array
    {
        return [
            'JPEG' => ['jpg', 'image/jpeg'],
            'PNG' => ['png', 'image/png'],
            'WEBP' => ['webp', 'image/webp'],
        ];
    }

    private function createActor(string $actorType): User
    {
        if ($actorType === 'head_coach') {
            return User::factory()->headCoach()->create([
                'age' => 30,
                'gender' => 'Laki-laki',
                'phone' => fake()->unique()->numerify('08##########'),
            ]);
        }

        return $this->createUser($actorType);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createUser(string $role, array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'role' => $role,
            'age' => 30,
            'gender' => 'Laki-laki',
            'phone' => fake()->unique()->numerify('08##########'),
        ], $attributes));
    }

    private function createMembership(User $admin, User $payer): Membership
    {
        $membership = Membership::query()->create([
            'user_id' => $payer->id,
            'admin_id' => $admin->id,
            'type' => 'membership',
            'base_price' => 500000,
            'discount_applied' => 0,
            'price_paid' => 500000,
            'total_paid' => 350000,
            'payment_status' => 'partial',
            'start_date' => '2026-08-01',
            'membership_end_date' => '2026-11-01',
            'status' => 'active',
        ]);

        $membership->members()->attach($payer);

        return $membership;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createTransaction(
        User $admin,
        User $payer,
        ?Membership $membership = null,
        array $attributes = [],
    ): MembershipTransaction {
        return MembershipTransaction::query()->create(array_merge([
            'invoice_number' => 'INV-TXN-'.Str::upper(Str::random(10)),
            'membership_id' => $membership?->id,
            'user_id' => $payer->id,
            'admin_id' => $admin->id,
            'shift' => $admin->shift,
            'transaction_type' => $membership ? 'Cicilan Membership' : 'Pemasukan Lain',
            'package_name' => $membership ? 'Paket Membership' : 'Biaya Harian',
            'amount' => 150000,
            'payment_method' => 'cash',
            'payment_date' => today()->toDateString(),
            'start_date' => $membership?->start_date?->toDateString(),
            'end_date' => $membership?->membership_end_date?->toDateString(),
            'notes' => null,
        ], $attributes));
    }

    /**
     * @return array<string, mixed>
     */
    private function invoiceViewData(
        MembershipTransaction $membershipTransaction,
        bool $includePaymentProof = false,
    ): array {
        return app(BuildMembershipTransactionInvoiceData::class)->execute(
            $membershipTransaction,
            $includePaymentProof,
        );
    }

    private function imageBytes(string $extension): string
    {
        $image = imagecreatetruecolor(1200, 800);
        $background = imagecolorallocate($image, 255, 225, 0);
        imagefill($image, 0, 0, $background);

        ob_start();

        match ($extension) {
            'jpg' => imagejpeg($image, null, 90),
            'png' => imagepng($image),
            'webp' => imagewebp($image, null, 90),
        };

        $contents = ob_get_clean();
        imagedestroy($image);

        return is_string($contents) ? $contents : '';
    }
}
