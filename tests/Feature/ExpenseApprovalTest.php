<?php

namespace Tests\Feature;

use App\Actions\ExpenseApproval;
use App\Actions\OperationalExpenseReport;
use App\Models\BeverageSale;
use App\Models\Expense;
use App\Models\ExpenseRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ExpenseApprovalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('jim_test', DB::connection()->getDatabaseName());
    }

    public function test_both_cashier_forms_submit_pending_without_posting_to_reports(): void
    {
        $cashier = User::factory()->create(['role' => 'kasir_gym']);
        Livewire::actingAs($cashier)->test('pages::dashboard.admin.pengeluaran.create')
            ->set('description', 'Kertas gym')->set('amount', 15000)->set('expense_date', '2026-10-12')
            ->call('save')->assertHasNoErrors()->assertRedirectToRoute('admin.pengeluaran.index');
        Livewire::actingAs($cashier)->test('pages::dashboard.admin.beverages.pos')
            ->set('expense_name', 'Form penjualan PT')->set('expense_amount', '15.000')->set('expense_date', '2026-10-12')
            ->set('nama_staff', 'Nama palsu')->call('saveExpense')->assertHasNoErrors()->assertDispatched('expense-approvals-updated');
        $this->assertDatabaseCount('expense_requests', 2);
        $this->assertSame(['pending'], ExpenseRequest::pluck('status')->unique()->values()->all());
        $this->assertSame([$cashier->name], ExpenseRequest::pluck('requested_by_name')->unique()->values()->all());
        $this->assertDatabaseCount('expenses', 0);
        $this->assertDatabaseCount('beverage_sales', 0);
        $this->assertSame(0, (new OperationalExpenseReport('2026-10-12', '2026-10-12'))->query()->count());
    }

    public function test_approval_posts_once_preserves_submission_and_refreshes_report(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $cashier = User::factory()->create(['role' => 'kasir_gym']);
        $action = app(ExpenseApproval::class);
        foreach (['gym', 'beverage'] as $source) {
            $request = $action->submit($cashier, $source, $this->input());
            Livewire::actingAs($admin)->test('dashboard.expense-approvals', ['source' => $source])
                ->call('approve', $request->id)->assertHasNoErrors()->assertDispatched('expense-approvals-updated');
            $action->approve($admin, $request->id);
            $request->refresh();
            $this->assertSame('approved', $request->status);
            $this->assertSame($admin->id, $request->decided_by);
            $this->assertNotNull($request->decided_at);
            $this->assertNotNull($source === 'gym' ? $request->expense_id : $request->beverage_sale_id);
        }
        $this->assertDatabaseCount('expenses', 1);
        $this->assertDatabaseCount('beverage_sales', 1);
        $this->assertSame($cashier->id, Expense::first()->admin_id);
        $this->assertSame('2026-10-12', Expense::first()->expense_date->toDateString());
        $this->assertSame('2026-10-12', BeverageSale::first()->waktu_transaksi->toDateString());
        $this->assertSame(30000, (new OperationalExpenseReport('2026-10-12', '2026-10-12'))->summary()['Total']['pengeluaran']);
    }

    public function test_admin_submission_is_automatically_approved_for_both_sources(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        foreach (['gym', 'beverage'] as $source) {
            $request = app(ExpenseApproval::class)->submit($admin, $source, $this->input());
            $this->assertSame('approved', $request->status);
            $this->assertSame($admin->id, $request->decided_by);
        }
        $this->assertDatabaseCount('expenses', 1);
        $this->assertDatabaseCount('beverage_sales', 1);
    }

    public function test_cashier_cannot_decide_and_only_sees_own_requests(): void
    {
        $cashier = User::factory()->create(['role' => 'kasir_gym']);
        $own = ExpenseRequest::factory()->create(['requested_by' => $cashier->id, 'description' => 'Pengajuan sendiri']);
        $other = ExpenseRequest::factory()->create(['description' => 'Pengajuan orang lain']);
        Livewire::actingAs($cashier)->test('dashboard.expense-approvals', ['source' => 'gym'])
            ->assertSee('Pengajuan sendiri')->assertDontSee('Pengajuan orang lain')->call('approve', $own->id)->assertForbidden();
        Livewire::actingAs($cashier)->test('dashboard.expense-approvals', ['source' => 'gym'])
            ->call('reject', $own->id)->assertForbidden();
        Livewire::actingAs($cashier)->test('dashboard.expense-approvals', ['source' => 'gym'])
            ->call('deletePending', $other->id)->assertForbidden();
        Livewire::actingAs($cashier)->test('dashboard.expense-approvals', ['source' => 'gym'])
            ->call('deletePending', $own->id)->assertHasNoErrors();
        $this->assertModelMissing($own);
        $this->assertModelExists($other);
    }

    public function test_rejection_requires_reason_and_cannot_later_be_approved(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        foreach (['gym', 'beverage'] as $source) {
            $request = ExpenseRequest::factory()->create(['source' => $source]);
            Livewire::actingAs($admin)->test('dashboard.expense-approvals', ['source' => $source])
                ->call('reject', $request->id)->assertHasErrors('rejection_reason')
                ->set('rejectionReasons.'.$request->id, 'Tidak sesuai kebutuhan')
                ->call('reject', $request->id)->assertHasNoErrors()
                ->call('approve', $request->id)->assertHasErrors('approval');
            $this->assertSame('rejected', $request->refresh()->status);
            $this->assertSame('Tidak sesuai kebutuhan', $request->rejection_reason);
        }
        $this->assertDatabaseCount('expenses', 0);
        $this->assertDatabaseCount('beverage_sales', 0);
    }

    public function test_invalid_expenses_and_unauthorized_sources_are_rejected(): void
    {
        $cashier = User::factory()->create(['role' => 'kasir_minum']);
        foreach (['amount' => 0, 'description' => '', 'expense_date' => 'not-a-date'] as $field => $value) {
            try {
                app(ExpenseApproval::class)->submit($cashier, 'beverage', array_replace($this->input(), [$field => $value]));
                $this->fail('Invalid expense was accepted.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey($field, $exception->errors());
            }
        }
        try {
            app(ExpenseApproval::class)->submit($cashier, 'gym', $this->input());
            $this->fail('Beverage cashier cannot submit gym expenses.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
        $this->assertDatabaseCount('expense_requests', 0);
    }

    public function test_sales_endpoint_cannot_bypass_expense_approval(): void
    {
        $cashier = User::factory()->create(['role' => 'kasir_gym']);
        $this->actingAs($cashier)->postJson(route('admin.beverages.pos.process'), ['keterangan_bayar' => 'pengeluaran_umum'])
            ->assertUnprocessable()->assertJsonValidationErrors('keterangan_bayar');
        $this->postJson(route('admin.beverages.pos.process'), ['keterangan_bayar' => 'deposit', 'secondary_payment_method' => 'pengeluaran_umum'])
            ->assertUnprocessable()->assertJsonValidationErrors('secondary_payment_method');
        $this->assertDatabaseCount('beverage_sales', 0);
    }

    public function test_navbar_counts_pending_expenses_and_refreshes_after_approval(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $gym = ExpenseRequest::factory()->create();
        ExpenseRequest::factory()->create(['source' => 'beverage']);
        ExpenseRequest::factory()->create(['status' => 'rejected']);
        $navbar = Livewire::actingAs($admin)->test('dashboard.navbar')
            ->assertSet('pendingOperationalCount', 2)->assertSet('pendingGymExpenseCount', 1)
            ->assertSet('pendingBeverageExpenseCount', 1)->assertSee('Pengeluaran Gym')->assertSee('Pengeluaran Minuman');
        app(ExpenseApproval::class)->approve($admin, $gym->id);
        $navbar->dispatch('expense-approvals-updated')->assertSet('pendingOperationalCount', 1);
    }

    public function test_approved_requests_cannot_be_rejected_deleted_or_reposted_after_transaction_deletion(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        foreach (['gym', 'beverage'] as $source) {
            $request = app(ExpenseApproval::class)->submit($admin, $source, $this->input());
            Livewire::actingAs($admin)->test('dashboard.expense-approvals', ['source' => $source])
                ->set('rejectionReasons.'.$request->id, 'Sudah diproses')
                ->call('reject', $request->id)->assertHasErrors('approval')
                ->call('deletePending', $request->id)->assertHasErrors('approval');
            if ($source === 'gym') {
                Expense::findOrFail($request->expense_id)->delete();
            } else {
                BeverageSale::findOrFail($request->beverage_sale_id)->delete();
            }
            app(ExpenseApproval::class)->approve($admin, $request->id);
            $this->assertSame('approved', $request->refresh()->status);
        }
        $this->assertDatabaseCount('expenses', 0);
        $this->assertDatabaseCount('beverage_sales', 0);
    }

    public function test_non_admin_sources_require_approval_and_members_cannot_submit(): void
    {
        foreach (['gym' => 'head_coach', 'beverage' => 'kasir_minum'] as $source => $role) {
            $user = User::factory()->create(['role' => $role]);
            $request = app(ExpenseApproval::class)->submit($user, $source, $this->input());
            $this->assertSame('pending', $request->status);
            Livewire::actingAs($user)->test('dashboard.expense-approvals', ['source' => $source])
                ->assertSee('Form training')->call('approve', $request->id)->assertForbidden();
        }
        $member = User::factory()->create(['role' => 'member']);
        Livewire::actingAs($member)->test('pages::dashboard.admin.pengeluaran.create')
            ->set('description', 'Tidak diizinkan')->set('amount', 15000)->set('expense_date', '2026-10-12')
            ->call('save')->assertForbidden();
        Livewire::actingAs($member)->test('dashboard.expense-approvals', ['source' => 'gym'])->assertForbidden();
        $this->assertDatabaseCount('expenses', 0);
        $this->assertDatabaseCount('beverage_sales', 0);
    }

    public function test_pos_validation_keeps_modal_open_and_does_not_submit(): void
    {
        $cashier = User::factory()->create(['role' => 'kasir_gym']);
        Livewire::actingAs($cashier)->test('pages::dashboard.admin.beverages.pos')
            ->set('expense_name', 'Kertas')->set('expense_amount', '-10')->set('expense_date', '2026-10-12')
            ->call('saveExpense')->assertHasErrors('amount')->assertNotDispatched('expense-submitted');
        $this->assertDatabaseCount('expense_requests', 0);
    }

    public function test_pages_render_approval_panels_and_approved_expense_enters_history(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get(route('admin.pengeluaran.index'))->assertOk()->assertSee('Approval Pengeluaran Gym');
        $this->get(route('admin.beverages.pos'))->assertOk()->assertSee('Approval Pengeluaran Minuman');
        $request = ExpenseRequest::factory()->create(['description' => 'Pembelian kertas pending']);
        $page = Livewire::actingAs($admin)->test('pages::dashboard.admin.pengeluaran.index');
        $this->assertSame(0, $page->get('expenses')->total());
        app(ExpenseApproval::class)->approve($admin, $request->id);
        $page->dispatch('expense-approvals-updated');
        $this->assertSame(1, $page->get('expenses')->total());
    }

    /** @return array{description: string, amount: int, expense_date: string} */
    private function input(): array
    {
        return ['description' => 'Form training', 'amount' => 15000, 'expense_date' => '2026-10-12'];
    }
}
