<?php

namespace Tests\Feature;

use App\Actions\BuildMembershipInvoiceData;
use App\Actions\BuildMembershipTransactionInvoiceData;
use App\Actions\StoreMembershipHold;
use App\Exports\PenjualanExport;
use App\Models\GymPackage;
use App\Models\Membership;
use App\Models\MembershipHold;
use App\Models\MembershipTransaction;
use App\Models\Shift;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Sheet;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MembershipHoldTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $cashier;

    private Membership $membership;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 7, 20)->startOfDay());
        Storage::fake('public');
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->cashier = User::factory()->create(['role' => 'kasir_gym', 'is_active' => true, 'shift' => Shift::factory()->create()->id]);
        $member = User::factory()->create(['role' => 'member', 'photo' => 'profile-photos/member.webp']);
        $package = GymPackage::create(['type' => 'pt', 'name' => 'PT 10 Sesi', 'category' => 'single', 'max_members' => 1, 'price' => 1000000, 'discount' => 0, 'is_active' => true]);
        $this->membership = Membership::create([
            'user_id' => $member->id, 'type' => 'pt', 'pt_package_id' => $package->id,
            'admin_id' => $this->cashier->id, 'follow_up_id' => $this->cashier->id, 'follow_up_id_two' => $this->cashier->id,
            'base_price' => 1000000, 'price_paid' => 1000000, 'total_paid' => 400000,
            'payment_status' => 'partial', 'total_sessions' => 10, 'remaining_sessions' => 5,
            'start_date' => '2026-06-30', 'pt_end_date' => '2026-07-30', 'status' => 'active', 'is_active' => true,
            'transaction_type' => 'NEW PT', 'package_name' => 'PT 10 Sesi', 'notes' => 'Paket awal',
        ]);
        $this->membership->members()->attach($member);
        $this->membership->transactions()->create([
            'invoice_number' => 'INV-ORIGINAL', 'user_id' => $member->id, 'admin_id' => $this->cashier->id,
            'follow_up_id' => $this->cashier->id, 'follow_up_id_two' => $this->cashier->id,
            'transaction_type' => 'NEW PT', 'package_name' => 'PT 10 Sesi', 'amount' => 400000,
            'payment_method' => 'cash', 'payment_date' => '2026-07-01', 'notes' => 'Pembayaran awal',
            'start_date' => '2026-06-30', 'end_date' => '2026-07-30',
        ]);
        $this->actingAs($this->admin);
    }

    #[DataProvider('durations')]
    public function test_calendar_months_and_prices_preserve_package_finances(string $oldDate, int $months, string $newDate): void
    {
        $this->membership->update(['pt_end_date' => $oldDate]);
        $form = $this->form()->set('months', $months)->call('save')->assertHasNoErrors()->assertSee('Hold berhasil disimpan');
        $hold = MembershipHold::sole();
        $this->assertSame($newDate, $this->membership->fresh()->pt_end_date->toDateString());
        $this->assertSame(200000, (int) $hold->total_amount);
        $this->assertNull($hold->monthly_price);
        $this->assertSame($oldDate, $hold->previous_end_date->toDateString());
        $this->assertSame(400000, (int) $this->membership->fresh()->total_paid);
        $this->assertSame('1000000', $this->membership->fresh()->price_paid);
        $this->assertSame('partial', $this->membership->fresh()->payment_status);
        $this->assertSame(5, $this->membership->fresh()->remaining_sessions);
        $this->assertSame(10, $this->membership->fresh()->total_sessions);
        $this->assertSame($this->cashier->shiftSnapshot(), $hold->transactions()->sole()->shift);
        $form->call('save')->assertHasNoErrors();
        $this->assertDatabaseCount('membership_holds', 1);
        $this->assertDatabaseCount('membership_transactions', 2);
    }

    public static function durations(): array
    {
        return [
            ['2026-07-30', 1, '2026-08-30'], ['2026-07-30', 2, '2026-09-30'],
            ['2026-07-30', 14, '2027-09-30'], ['2026-01-31', 1, '2026-02-28'],
            ['2028-01-31', 1, '2028-02-29'], ['2028-02-29', 12, '2029-02-28'],
        ];
    }

    public function test_split_payment_stores_one_hold_and_only_positive_payment_rows(): void
    {
        $this->form()->set('months', 2)->set('total_amount', 400000)->set('is_split_payment', true)
            ->set('amounts.cash', 100000)->set('amounts.transfer', 200000)->set('amounts.qris', 100000)
            ->set('proofs.transfer', UploadedFile::fake()->image('transfer.jpg'))
            ->set('proofs.qris', UploadedFile::fake()->image('qris.png'))
            ->call('save')->assertHasNoErrors()->assertSee('Unduh Invoice TRANSFER');
        $hold = MembershipHold::sole();
        $this->assertCount(3, $hold->transactions);
        $this->assertSame(400000, (int) $hold->transactions->sum('amount'));
        foreach ($hold->transactions as $transaction) {
            $this->assertSame($this->membership->id, $transaction->membership_id);
            if ($transaction->payment_method !== 'cash') {
                Storage::disk('public')->assertExists($transaction->payment_proof_path);
            }
        }
        $this->assertSame('2026-09-30', $this->membership->fresh()->pt_end_date->toDateString());
    }

    #[DataProvider('invalidInputs')]
    public function test_invalid_payments_and_months_are_rejected(array $values, string $error): void
    {
        $form = $this->form();
        foreach ($values as $key => $value) {
            $form->set($key, $value);
        }
        $form->call('save')->assertHasErrors($error);
        $this->assertDatabaseCount('membership_holds', 0);
        $this->assertSame('2026-07-30', $this->membership->fresh()->pt_end_date->toDateString());
    }

    public static function invalidInputs(): array
    {
        return [
            [['total_amount' => ''], 'total_amount'], [['total_amount' => 0], 'total_amount'],
            [['total_amount' => -1], 'total_amount'], [['total_amount' => 1000.5], 'total_amount'],
            [['total_amount' => 'abc'], 'total_amount'], [['total_amount' => 1000000000000], 'total_amount'],
            [['months' => 0], 'months'], [['months' => -1], 'months'], [['months' => 1.5], 'months'],
            [['months' => 'abc'], 'months'], [['months' => 999999999], 'months'],
            [['payment_method' => 'other'], 'payment_method'], [['payment_date' => 'not-a-date'], 'payment_date'],
            [['payment_method' => 'transfer'], 'proofs.transfer'], [['payment_method' => 'debit'], 'proofs.debit'],
            [['is_split_payment' => true, 'amounts.cash' => 100000], 'amounts'],
            [['is_split_payment' => true, 'amounts.cash' => -200000], 'amounts.cash'],
            [['is_split_payment' => true, 'amounts.cash' => 200000.5], 'amounts.cash'],
            [['is_split_payment' => true, 'amounts.qris' => 200000], 'proofs.qris'],
        ];
    }

    public function test_invalid_proof_and_inactive_cashier_are_rejected(): void
    {
        $this->form()->set('payment_method', 'qris')->set('proofs.qris', UploadedFile::fake()->create('file.pdf'))
            ->call('save')->assertHasErrors('proofs.qris');
        $this->cashier->update(['is_active' => false]);
        $this->form()->call('save')->assertHasErrors('admin_id');
    }

    #[DataProvider('ineligibleMemberships')]
    public function test_ineligible_memberships_cannot_be_held(array $changes): void
    {
        $form = $this->form();
        $this->membership->update($changes);
        $form->call('save')->assertHasErrors('membership');
        $this->assertDatabaseCount('membership_holds', 0);
    }

    public static function ineligibleMemberships(): array
    {
        return [[['remaining_sessions' => 0]], [['remaining_sessions' => null]], [['status' => 'pending']],
            [['status' => 'rejected']], [['pt_end_date' => null]], [['type' => 'membership']], [['type' => 'bundle_pt_membership']]];
    }

    public function test_only_admin_can_open_and_submit_hold_and_only_pt_shows_the_button(): void
    {
        $this->actingAs($this->cashier)->get(route('admin.membership.hold', $this->membership))->assertRedirect(route('home'));
        $this->actingAs($this->admin);
        $this->get(route('admin.membership.hold', $this->membership))->assertOk();
        $this->get(route('admin.riwayat.detail', $this->membership->user_id))->assertSee('>Hold</a>', false);
        $this->membership->update(['type' => 'bundle_pt_membership']);
        $this->get(route('admin.membership.hold', $this->membership))->assertNotFound();
        $this->get(route('admin.riwayat.detail', $this->membership->user_id))->assertDontSee('>Hold</a>', false);
        $this->membership->update(['type' => 'pt']);
        $form = $this->form();
        $this->actingAs($this->cashier);
        $form->call('save')->assertForbidden();
        $this->assertDatabaseCount('membership_holds', 0);
    }

    public function test_expired_package_reactivates_only_when_new_date_is_valid(): void
    {
        $this->travelTo(now()->setDate(2026, 9, 18));
        $this->membership->update(['status' => 'completed', 'is_active' => false]);
        $this->form()->call('save')->assertHasNoErrors()->assertSee('Paket belum aktif kembali');
        $this->assertSame('completed', $this->membership->fresh()->status);
        $this->membership->refresh();
        $this->form()->call('save')->assertHasNoErrors();
        $this->assertSame('2026-09-30', $this->membership->fresh()->pt_end_date->toDateString());
        $this->assertSame('active', $this->membership->fresh()->status);
        $this->assertTrue($this->membership->fresh()->is_active);
    }

    public function test_stale_form_refreshes_preview_and_requires_another_submit(): void
    {
        $stale = $this->form();
        $this->form()->call('save')->assertHasNoErrors();
        $stale->call('save')->assertHasErrors('expectedEndDate')->assertSet('expectedEndDate', '2026-08-30');
        $this->assertDatabaseCount('membership_holds', 1);
        $stale->call('save')->assertHasNoErrors();
        $this->assertSame('2026-09-30', $this->membership->fresh()->pt_end_date->toDateString());
    }

    public function test_action_replay_is_idempotent_and_uses_manual_total(): void
    {
        $token = (string) Str::uuid();
        $input = array_replace($this->input(), ['months' => 3, 'total_amount' => 175001, 'monthly_price' => 1]);
        $action = app(StoreMembershipHold::class);
        $hold = $action->execute($this->membership, $this->admin, $token, '2026-07-30', $input);
        $replayed = $action->execute($this->membership, $this->admin, $token, '2026-07-30', $input);
        $this->assertTrue($hold->is($replayed));
        $this->assertSame('175001', $hold->total_amount);
        $this->assertNull($hold->monthly_price);
        $this->assertSame('175001', $hold->transactions()->sole()->amount);
        $description = view('components.membership-hold-description', ['hold' => $hold])->render();
        $this->assertStringContainsString('175.001', $description);
        $this->assertStringNotContainsString('×', $description);
        $this->assertDatabaseCount('membership_holds', 1);
    }

    public function test_failed_transaction_rolls_back_hold_date_and_uploaded_proof(): void
    {
        $fail = true;
        MembershipTransaction::creating(function (MembershipTransaction $transaction) use (&$fail): void {
            if ($fail && $transaction->membership_hold_id) {
                $fail = false;
                throw new \RuntimeException('Hold transaction failure');
            }
        });
        try {
            app(StoreMembershipHold::class)->execute($this->membership, $this->admin, (string) Str::uuid(), '2026-07-30', array_replace($this->input(), [
                'payment_method' => 'transfer', 'proofs' => ['transfer' => UploadedFile::fake()->image('proof.jpg')],
            ]));
            $this->fail('Expected transaction failure');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Hold transaction failure', $exception->getMessage());
        }
        $this->assertDatabaseCount('membership_holds', 0);
        $this->assertDatabaseCount('membership_transactions', 1);
        $this->assertSame('2026-07-30', $this->membership->fresh()->pt_end_date->toDateString());
        $this->assertSame([], Storage::disk('public')->allFiles('membership-payment-proofs'));
    }

    public function test_sales_and_both_invoices_include_hold_without_reducing_package_debt(): void
    {
        $this->form()->call('save')->assertHasNoErrors();
        $transaction = MembershipHold::sole()->transactions()->sole();
        $sales = Livewire::test('pages::dashboard.admin.penjualan.index')->assertSee('HOLD PT')->assertSee($transaction->invoice_number);
        $this->assertSame(0, (int) $sales->get('summary')['uang_pt']);
        $this->assertSame(200000, (int) $sales->get('summary')['uang_hold']);
        $sales->set('shift', $this->cashier->shiftSnapshot())->assertSee($transaction->invoice_number);
        $sales->set('filterTime', 'custom')->set('dateStart', '2026-07-21')->set('dateEnd', '2026-07-22')->assertDontSee($transaction->invoice_number);

        $data = app(BuildMembershipInvoiceData::class)->execute($this->membership->fresh());
        $this->assertSame(600000.0, $data['remainingBalance']);
        $this->assertSame(1200000, $data['overallTotal']);
        $this->assertSame(600000, $data['overallPaid']);
        $html = view('pages.dashboard.admin.riwayat.invoice-pdf', $data)->render();
        $this->assertStringContainsString('HOLD PT 1 bulan', $html);
        $this->assertStringContainsString('NEW PT', $html);
        $this->get($data['verificationUrl'])->assertOk()->assertSee('HOLD PT 1 bulan')->assertSee('INV-ORIGINAL');
        $transactionData = app(BuildMembershipTransactionInvoiceData::class)->execute($transaction);
        file_put_contents(storage_path('framework/testing/hold-transaction-review.pdf'), Pdf::loadView('pages.dashboard.admin.penjualan.invoice-pdf', $transactionData)->setPaper('a4')->output());
        $this->get($transactionData['verificationUrl'])->assertOk()->assertSee('HOLD PT 1 bulan')->assertSee('30/08/2026');
        foreach (['admin.penjualan.invoice' => $transaction, 'admin.riwayat.membership.invoice' => $this->membership] as $route => $model) {
            $response = $this->get(route($route, $model))->assertOk()->assertHeader('content-type', 'application/pdf');
            $this->assertStringStartsWith('%PDF', $response->getContent());
        }
    }

    public function test_edit_and_installment_keep_hold_separate_from_package_payments(): void
    {
        $this->form()->call('save')->assertHasNoErrors();
        $holdTransaction = MembershipHold::sole()->transactions()->sole();
        $snapshot = $holdTransaction->getAttributes();
        $edit = Livewire::test('pages::dashboard.admin.membership.edit', ['id' => $this->membership->id])
            ->assertSet('transaction_type', 'NEW PT')->assertSee('Riwayat Hold PT');
        $this->assertCount(1, $edit->get('transactions'));
        $edit->call('save')->assertHasNoErrors();
        $this->assertSame(400000, (int) $this->membership->fresh()->total_paid);
        $this->assertSame($snapshot, $holdTransaction->fresh()->getAttributes());

        Livewire::test('pages::dashboard.admin.cicilan.pay', ['membership' => $this->membership->fresh()])
            ->set('amount_paid', 100000)->set('transaction_type', 'CICILAN PT')->set('notes', 'Cicilan paket')
            ->call('save')->assertHasNoErrors();
        $this->assertSame(500000, (int) $this->membership->fresh()->total_paid);
        $this->assertSame('PT 10 Sesi', $this->membership->packageTransactions()->latest('id')->first()->package_name);
        $this->assertSame($snapshot, $holdTransaction->fresh()->getAttributes());
    }

    public function test_edit_rejects_injected_hold_transaction(): void
    {
        $this->form()->call('save')->assertHasNoErrors();
        $transaction = MembershipHold::sole()->transactions()->sole();
        Livewire::test('pages::dashboard.admin.membership.edit', ['id' => $this->membership->id])
            ->set('transactions.0.id', $transaction->id)->call('save')->assertHasErrors('transactions.0.id');
        $this->assertSame('200000', $transaction->fresh()->amount);
    }

    public function test_long_membership_invoice_keeps_all_hold_rows_and_renders_multiple_pages(): void
    {
        for ($index = 0; $index < 18; $index++) {
            $this->membership->refresh();
            app(StoreMembershipHold::class)->execute(
                $this->membership, $this->admin, (string) Str::uuid(),
                $this->membership->pt_end_date->toDateString(), $this->input(),
            );
        }

        $data = app(BuildMembershipInvoiceData::class)->execute($this->membership->fresh());
        $this->assertCount(19, $data['membership']->transactions);
        $this->assertSame(3600000, $data['holdTotal']);
        $html = view('pages.dashboard.admin.riwayat.invoice-pdf', $data)->render();
        $this->assertSame(18, substr_count($html, 'HOLD PT 1 bulan'));
        $pdf = Pdf::loadHTML($html)->setPaper('a4');
        $output = $pdf->output();
        $this->assertStringStartsWith('%PDF', $output);
        $this->assertGreaterThan(1, $pdf->getDomPDF()->getCanvas()->get_page_count());
        file_put_contents(storage_path('framework/testing/hold-invoice-review.pdf'), $output);
    }

    private function form(): Testable
    {
        return Livewire::test('pages::dashboard.admin.membership.hold', ['membership' => $this->membership->fresh()])
            ->set('admin_id', $this->cashier->id)->set('total_amount', 200000);
    }

    public function test_hold_has_its_own_sales_and_excel_total_without_double_counting(): void
    {
        $this->form()->set('total_amount', 175001)->set('is_split_payment', true)
            ->set('amounts.cash', 75000)->set('amounts.transfer', 100001)
            ->set('proofs.transfer', UploadedFile::fake()->image('transfer.jpg'))
            ->call('save')->assertHasNoErrors();

        $sales = Livewire::test('pages::dashboard.admin.penjualan.index')
            ->set('filterTime', 'custom')->set('dateStart', '2026-07-01')->set('dateEnd', '2026-07-20')
            ->assertSeeInOrder(['NIMBANG', 'HOLD', 'BALANCE']);
        $summary = $sales->get('summary');
        $this->assertSame(175001, (int) $summary['uang_hold']);
        $this->assertSame(400000, (int) $summary['uang_pt']);
        $this->assertSame(0, (int) $summary['uang_member']);
        $this->assertSame(575001, (int) $summary['uang_total']);
        $this->assertEquals($summary['uang_total'], $summary['uang_member'] + $summary['uang_visit'] + $summary['uang_pt'] + $summary['uang_nimbang'] + $summary['uang_hold']);

        Excel::shouldReceive('download')->once()
            ->andReturnUsing(function (PenjualanExport $export, string $filename) {
                $sheet = (new Spreadsheet)->getActiveSheet();
                $export->registerEvents()[AfterSheet::class](
                    new AfterSheet(new Sheet($sheet), $export),
                );
                $holdRow = null;
                for ($row = 1; $row <= $sheet->getHighestRow(); $row++) {
                    if ($sheet->getCell('C'.$row)->getValue() === 'HOLD:') {
                        $holdRow = $row;
                        break;
                    }
                }
                $this->assertNotNull($holdRow);
                $this->assertSame('NIMBANG:', $sheet->getCell('C'.($holdRow - 1))->getValue());
                $this->assertEquals(175001, $sheet->getCell('D'.$holdRow)->getValue());
                $this->assertEquals(400000, $sheet->getCell('D'.($holdRow - 2))->getValue());
                $this->assertSame('BALANCE', $sheet->getCell('C'.($holdRow + 1))->getValue());
                $this->assertEquals(575001, $sheet->getCell('D'.($holdRow + 1))->getValue());
                $this->assertSame('CATATAN PENGELUARAN', $sheet->getCell('C'.($holdRow + 2))->getValue());
                $this->assertTrue($sheet->getStyle('D'.($holdRow + 1))->getFont()->getBold());

                return response('', 200);
            });
        $sales->call('exportExcel')->assertHasNoErrors();
        $sales->set('shift', 'Tidak ada shift');
        $this->assertSame(0, (int) $sales->get('summary')['uang_hold']);
        $sales->set('shift', '')->set('dateStart', '2026-07-21')->set('dateEnd', '2026-07-22');
        $this->assertSame(0, (int) $sales->get('summary')['uang_hold']);
    }

    public function test_manual_amount_starts_empty_and_does_not_change_with_duration(): void
    {
        $form = Livewire::test('pages::dashboard.admin.membership.hold', ['membership' => $this->membership])
            ->assertSet('total_amount', '')->assertSee('Nominal Total Hold (Rp)')
            ->assertDontSee('Rp200.000 per bulan')
            ->set('admin_id', $this->cashier->id)->set('total_amount', 175001)->set('months', 3);
        $this->assertSame(175001, $form->get('totalAmount'));
        $form->call('save')->assertHasNoErrors();
        $this->assertSame('175001', MembershipHold::sole()->transactions()->sole()->amount);
    }

    public function test_legacy_hold_invoice_retains_recorded_monthly_rate(): void
    {
        $hold = MembershipHold::factory()->for($this->membership)->create([
            'months' => 2, 'monthly_price' => 200000, 'total_amount' => 400000,
        ]);
        $description = view('components.membership-hold-description', ['hold' => $hold])->render();
        $this->assertStringContainsString('× Rp 200.000', $description);
        $this->assertStringContainsString('400.000', $description);
    }

    /** @return array<string, mixed> */
    private function input(): array
    {
        return ['months' => 1, 'total_amount' => 200000, 'payment_date' => '2026-07-20', 'admin_id' => $this->cashier->id, 'notes' => '',
            'is_split_payment' => false, 'payment_method' => 'cash', 'amounts' => [], 'proofs' => []];
    }
}
