<?php

namespace Tests\Feature;

use App\Actions\BuildMembershipInvoiceData;
use App\Actions\BuildMembershipTransactionInvoiceData;
use App\Models\Membership;
use App\Models\MembershipOperationalRequest;
use App\Models\MembershipTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class MembershipOperationalReportingTest extends TestCase
{
    use RefreshDatabase;

    public function test_operational_membership_has_no_bonus_but_keeps_covered_value(): void
    {
        [$membership, $transaction, $admin] = $this->operational();
        $this->assertTrue($membership->isOperational());
        $this->assertTrue($transaction->isOperational());
        $this->assertSame(0.0, $membership->calculateNominalAkhir());
        $this->assertFalse(Membership::forBonusRecipient($admin)->whereKey($membership->id)->exists());
        $this->assertSame(100000, (int) $membership->total_paid);
        $this->assertSame('paid', $membership->payment_status);
    }

    public function test_penjualan_separates_operational_value_from_money_totals(): void
    {
        [$membership, $transaction, $admin] = $this->operational();
        $transaction->replicate(['operational_request_id', 'invoice_number'])->fill(['invoice_number' => 'INV-CASH-TEST', 'payment_method' => 'cash', 'amount' => 20000])->save();
        $page = Livewire::actingAs($admin)->test('pages::dashboard.admin.penjualan.index');
        $summary = $page->get('summary');
        $this->assertEquals(100000, $summary['operasional']);
        $this->assertEquals(20000, $summary['uang_total']);
        $this->assertEquals(20000, $summary['uang_pt']);
        $this->assertEquals(0, $summary['uang_member']);
        $page->assertSee('OPERASIONAL');
    }

    public function test_invoice_describes_operational_coverage_without_money_received(): void
    {
        [$membership, $transaction] = $this->operational();
        $data = app(BuildMembershipTransactionInvoiceData::class)->execute($transaction);
        $this->assertSame('DITANGGUNG OPERASIONAL', $data['paymentStatusLabel']);
        $html = view('pages.dashboard.admin.penjualan.invoice-pdf', $data)->render();
        $this->assertStringContainsString('tidak ada uang diterima', $html);
        $this->assertStringNotContainsString('TOTAL DIBAYAR', $html);
        $data = app(BuildMembershipInvoiceData::class)->execute($membership);
        $this->assertSame('DITANGGUNG OPERASIONAL', $data['paymentStatusLabel']);
        $this->assertSame(0, $data['overallPaid']);
        $this->assertEquals(0, $data['remainingBalance']);
    }

    private function operational(): array
    {
        $admin = User::factory()->create(['role' => 'kasir_gym']);
        $member = User::factory()->create(['role' => 'member']);
        $request = MembershipOperationalRequest::create(['submission_token' => (string) Str::uuid(), 'requested_by' => $admin->id, 'requested_by_name' => $admin->name, 'status' => 'approved', 'reason' => 'Internal', 'requested_at' => now(), 'snapshot' => [], 'documents' => []]);
        $membership = Membership::create(['operational_request_id' => $request->id, 'user_id' => $member->id, 'admin_id' => $admin->id, 'type' => 'pt', 'base_price' => 100000, 'price_paid' => 100000, 'total_paid' => 100000, 'payment_status' => 'paid', 'status' => 'active', 'follow_up_id' => null, 'follow_up_id_two' => null]);
        $transaction = MembershipTransaction::create(['operational_request_id' => $request->id, 'membership_id' => $membership->id, 'user_id' => $member->id, 'admin_id' => $admin->id, 'invoice_number' => 'INV-OP-TEST', 'amount' => 100000, 'payment_method' => 'operasional', 'payment_date' => today(), 'transaction_type' => 'Baru', 'package_name' => 'PT Internal']);

        return [$membership, $transaction, $admin];
    }
}
