<?php

namespace Tests\Feature;

use App\Actions\BuildMembershipInvoiceData;
use App\Models\GymPackage;
use App\Models\Membership;
use App\Models\MembershipTransaction;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as PdfDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Mockery;
use Tests\TestCase;

class MembershipInvoiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_user_can_download_membership_invoice_with_payment_history(): void
    {
        $membership = $this->createMembershipWithInstallments();
        $admin = $this->createUser('admin');

        $response = $this->actingAs($admin)->get(route('admin.riwayat.membership.invoice', $membership));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $response->assertHeader('content-disposition', 'attachment; filename=Invoice_Membership_'.$membership->id.'_'.str($membership->user->name)->slug('_').'.pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_invoice_hides_admin_fee_but_keeps_the_total_bill(): void
    {
        $membership = $this->createMembershipWithInstallments();

        $this->view('pages.dashboard.admin.riwayat.invoice-pdf', $this->invoiceViewData($membership))
            ->assertDontSee('Biaya Admin')
            ->assertSee('Total Tagihan')
            ->assertSee('Rp 320.000');
    }

    public function test_invoice_uses_latest_transaction_for_header_metadata(): void
    {
        $membership = $this->createMembershipWithInstallments();
        $admin = $this->createUser('admin');

        $pdf = Mockery::mock(PdfDocument::class);
        $pdf->shouldReceive('setPaper')->once()->with('a4')->andReturnSelf();
        $pdf->shouldReceive('download')->once()->andReturn(response('%PDF invoice', 200, [
            'Content-Type' => 'application/pdf',
        ]));

        Pdf::shouldReceive('loadView')
            ->once()
            ->with('pages.dashboard.admin.riwayat.invoice-pdf', Mockery::on(function (array $data) use ($membership): bool {
                $this->assertSame('INV-TEST-'.$membership->id.'-2', $data['invoiceNumber']);
                $this->assertSame('TRANSFER', $data['paymentMethod']);
                $this->assertSame('SEBAGIAN', $data['paymentStatusLabel']);
                $this->assertSame('FG-'.str_pad((string) $membership->user_id, 6, '0', STR_PAD_LEFT), $data['memberNumber']);
                $this->assertStringStartsWith('data:image/svg+xml;base64,', $data['qrCodeDataUri']);
                $this->assertTrue(URL::hasValidSignature(
                    request()->create($data['verificationUrl']),
                ));

                return true;
            }))
            ->andReturn($pdf);

        $this->actingAs($admin)
            ->get(route('admin.riwayat.membership.invoice', $membership))
            ->assertOk();
    }

    public function test_invoice_uses_membership_fallback_when_there_are_no_transactions(): void
    {
        $membership = $this->createMembershipWithInstallments();
        $membership->transactions()->delete();
        $admin = $this->createUser('admin');

        $pdf = Mockery::mock(PdfDocument::class);
        $pdf->shouldReceive('setPaper')->once()->with('a4')->andReturnSelf();
        $pdf->shouldReceive('download')->once()->andReturn(response('%PDF invoice', 200, [
            'Content-Type' => 'application/pdf',
        ]));

        Pdf::shouldReceive('loadView')
            ->once()
            ->with('pages.dashboard.admin.riwayat.invoice-pdf', Mockery::on(function (array $data) use ($membership): bool {
                $this->assertSame('MEM-'.str_pad((string) $membership->id, 6, '0', STR_PAD_LEFT), $data['invoiceNumber']);
                $this->assertTrue($membership->created_at->equalTo($data['invoiceDate']));
                $this->assertSame('-', $data['paymentMethod']);

                return true;
            }))
            ->andReturn($pdf);

        $this->actingAs($admin)
            ->get(route('admin.riwayat.membership.invoice', $membership))
            ->assertOk();
    }

    public function test_invoice_contains_brand_sections_and_verification_qr(): void
    {
        $membership = $this->createMembershipWithInstallments();

        $this->view('pages.dashboard.admin.riwayat.invoice-pdf', $this->invoiceViewData($membership))
            ->assertSee('FRANS')
            ->assertSee('Data Member')
            ->assertSee('Detail Membership')
            ->assertSee('Rincian Pembayaran')
            ->assertSee('Riwayat Pembayaran')
            ->assertSee('Ketentuan Pembayaran')
            ->assertSee('Terima Kasih!')
            ->assertSee('Scan untuk verifikasi')
            ->assertSee('Verifikasi keaslian invoice')
            ->assertDontSee('@fransgym')
            ->assertDontSee('www.fransgym')
            ->assertDontSee('Jambi, Indonesia');
    }

    public function test_guest_can_verify_invoice_using_a_valid_signed_url(): void
    {
        $membership = $this->createMembershipWithInstallments();
        $verificationUrl = URL::signedRoute('membership.invoice.verify', [
            'membership' => $membership,
        ]);

        $this->get($verificationUrl)
            ->assertOk()
            ->assertSee('Invoice Terverifikasi')
            ->assertSee('INV-TEST-'.$membership->id.'-2')
            ->assertSee($membership->user->name)
            ->assertSee('FG-'.str_pad((string) $membership->user_id, 6, '0', STR_PAD_LEFT));
    }

    public function test_unsigned_invoice_verification_url_is_forbidden(): void
    {
        $membership = $this->createMembershipWithInstallments();

        $this->get(route('membership.invoice.verify', $membership))
            ->assertForbidden();
    }

    public function test_tampered_invoice_verification_url_is_forbidden(): void
    {
        $membership = $this->createMembershipWithInstallments();
        $verificationUrl = URL::signedRoute('membership.invoice.verify', [
            'membership' => $membership,
        ]);

        $this->get($verificationUrl.'&tampered=1')
            ->assertForbidden();
    }

    public function test_invoice_renders_all_payment_statuses_and_balances(): void
    {
        $membership = $this->createMembershipWithInstallments();

        foreach ([
            ['unpaid', 'BELUM LUNAS', 320000],
            ['partial', 'SEBAGIAN', 120000],
            ['paid', 'LUNAS', 0],
        ] as [$status, $label, $remainingBalance]) {
            $membership->payment_status = $status;
            $membership->total_paid = 320000 - $remainingBalance;

            $this->view('pages.dashboard.admin.riwayat.invoice-pdf', $this->invoiceViewData($membership))
                ->assertSee($label)
                ->assertSee('Rp '.number_format($remainingBalance, 0, ',', '.'));
        }
    }

    public function test_invoice_does_not_truncate_long_payment_history(): void
    {
        $membership = $this->createMembershipWithInstallments();

        foreach (range(3, 17) as $installment) {
            MembershipTransaction::create([
                'invoice_number' => 'INV-LONG-'.$membership->id.'-'.$installment,
                'membership_id' => $membership->id,
                'user_id' => $membership->user_id,
                'admin_id' => $membership->admin_id,
                'transaction_type' => 'Cicilan panjang '.$installment,
                'package_name' => $membership->gymPackage->name,
                'amount' => 10000,
                'payment_method' => 'transfer',
                'payment_date' => now()->addDays($installment)->toDateString(),
            ]);
        }

        $this->view('pages.dashboard.admin.riwayat.invoice-pdf', $this->invoiceViewData($membership->fresh()))
            ->assertSee('Cicilan 1')
            ->assertSee('Cicilan panjang 17');
    }

    public function test_cashier_can_download_membership_invoice(): void
    {
        $membership = $this->createMembershipWithInstallments();
        $cashier = $this->createUser('kasir_gym');

        $this->actingAs($cashier)
            ->get(route('admin.riwayat.membership.invoice', $membership))
            ->assertOk();
    }

    public function test_head_coach_can_download_membership_invoice(): void
    {
        $membership = $this->createMembershipWithInstallments();
        $headCoach = $this->createUser('head_coach');

        $this->actingAs($headCoach)
            ->get(route('admin.riwayat.membership.invoice', $membership))
            ->assertOk();
    }

    public function test_member_cannot_download_membership_invoice(): void
    {
        $membership = $this->createMembershipWithInstallments();
        $member = $this->createUser('member');

        $this->actingAs($member)
            ->get(route('admin.riwayat.membership.invoice', $membership))
            ->assertRedirect(route('home'));
    }

    private function createMembershipWithInstallments(): Membership
    {
        $member = $this->createUser('member');
        $admin = $this->createUser('admin');
        $package = GymPackage::create([
            'type' => 'gym',
            'name' => 'Paket Gym Bulanan',
            'category' => 'single',
            'max_members' => 1,
            'price' => 300000,
            'discount' => 0,
            'is_active' => true,
        ]);

        $membership = Membership::create([
            'user_id' => $member->id,
            'admin_id' => $admin->id,
            'type' => 'membership',
            'gym_package_id' => $package->id,
            'base_price' => 300000,
            'discount_applied' => 0,
            'admin_fee' => 20000,
            'price_paid' => 320000,
            'total_paid' => 200000,
            'payment_status' => 'partial',
            'start_date' => now()->toDateString(),
            'membership_end_date' => now()->addMonth()->toDateString(),
            'status' => 'pending',
        ]);

        $membership->members()->attach($member);

        foreach ([100000, 100000] as $index => $amount) {
            MembershipTransaction::create([
                'invoice_number' => 'INV-TEST-'.$membership->id.'-'.($index + 1),
                'membership_id' => $membership->id,
                'user_id' => $member->id,
                'admin_id' => $admin->id,
                'transaction_type' => 'Cicilan '.($index + 1),
                'package_name' => $package->name,
                'amount' => $amount,
                'payment_method' => 'transfer',
                'payment_date' => now()->addDays($index)->toDateString(),
            ]);
        }

        return $membership;
    }

    /**
     * @return array<string, mixed>
     */
    private function invoiceViewData(Membership $membership): array
    {
        return app(BuildMembershipInvoiceData::class)->execute($membership);
    }

    private function createUser(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'age' => 30,
            'gender' => 'Laki-laki',
            'phone' => fake()->unique()->numerify('08##########'),
        ]);
    }
}
