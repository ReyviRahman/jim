<?php

namespace Tests\Feature;

use App\Actions\OperationalExpenseReport;
use App\Exports\OperationalExpenseExport;
use App\Models\BeverageOperationalRequest;
use App\Models\BeverageSale;
use App\Models\Expense;
use App\Models\MembershipOperationalRequest;
use App\Models\MembershipTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class OperationalExpenseReportTest extends TestCase
{
    use RefreshDatabase;

    private const COMPONENT = 'pages::dashboard.admin.operasional-pengeluaran.index';

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 1)->setTime(12, 0));
        $this->assertSame('jim_test', DB::connection()->getDatabaseName());
    }

    public function test_admin_and_gym_cashier_have_access_with_all_shifts_by_default(): void
    {
        foreach (['admin', 'kasir_gym'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user)->get(route('admin.operasional-pengeluaran.index'))
                ->assertOk()->assertSeeHtml('href="'.route('admin.operasional-pengeluaran.index').'"');
            Livewire::actingAs($user)->test(self::COMPONENT)
                ->assertSet('dateStart', '2026-10-01')->assertSet('dateEnd', '2026-10-31')->assertSet('source', '')->assertSet('kind', '')->assertSet('shift', '')
                ->assertSee('Tidak ada data operasional atau pengeluaran sesuai filter.');
        }
    }

    public function test_other_roles_and_guests_cannot_access_report(): void
    {
        $this->get(route('admin.operasional-pengeluaran.index'))->assertRedirect(route('login'));
        $users = [];
        foreach (['member', 'kasir_minum', 'pt', 'sales'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $users[] = $user;
            $this->actingAs($user)->get(route('admin.operasional-pengeluaran.index'))->assertRedirect(route('home'));
        }
        $headCoach = User::factory()->headCoach()->create();
        $this->actingAs($headCoach)->get(route('admin.operasional-pengeluaran.index'))->assertRedirect(route('home'));
        $this->get(route('admin.cicilan.index'))->assertDontSeeHtml('href="'.route('admin.operasional-pengeluaran.index').'"');
        foreach ([...$users, $headCoach] as $user) {
            Livewire::actingAs($user)->test(self::COMPONENT)->assertForbidden();
        }
    }

    public function test_all_four_sources_are_counted_separately_without_unrelated_payments_or_pending_requests(): void
    {
        $user = $this->fixture();
        foreach (['cash', 'deposit', 'hutang', 'deposit_hutang_cash'] as $method) {
            $this->sale($method, 999999);
        }
        MembershipTransaction::firstOrFail()->replicate(['invoice_number'])
            ->fill(['invoice_number' => 'INV-CASH', 'payment_method' => 'cash', 'amount' => 999999])->save();
        MembershipOperationalRequest::create(['submission_token' => (string) Str::uuid(), 'requested_by' => $user->id, 'requested_by_name' => $user->name, 'status' => 'pending', 'reason' => 'Pending', 'requested_at' => now(), 'snapshot' => [], 'documents' => []]);
        BeverageOperationalRequest::create(['requested_by' => $user->id, 'nama_staff' => $user->name, 'shift' => 'pagi', 'reason' => 'Pending', 'status' => 'pending', 'requested_at' => now(), 'total' => 999999]);
        $report = new OperationalExpenseReport('2026-10-01', '2026-10-01');
        $this->assertSame(4, $report->query()->count());
        Livewire::actingAs($user)->test(self::COMPONENT)
            ->assertSee('Total Operasional dan Pengeluaran')->assertSee('Rp 40.000');
        $this->assertSame([
            'Gym' => ['operasional' => 10000, 'pengeluaran' => 10000],
            'Minuman' => ['operasional' => 10000, 'pengeluaran' => 10000],
            'Total' => ['operasional' => 20000, 'pengeluaran' => 20000],
        ], $report->summary());
        $this->assertCount(4, $report->details()->get()->map(fn ($row) => $row->record_source.'-'.$row->record_id)->unique());
    }

    public function test_filters_date_boundaries_and_historical_shifts_apply_to_summary_and_details(): void
    {
        $user = $this->fixture();
        $this->sale('operasional', 20000, '2026-09-30 23:59:59', 'Legacy');
        $this->sale('operasional', 30000, '2026-10-01 23:59:59', 'Legacy');
        $this->sale('operasional', 40000, '2026-10-02 00:00:00', 'Legacy');
        $report = new OperationalExpenseReport('2026-10-01', '2026-10-01', 'Minuman', 'Operasional', 'legacy');
        $this->assertSame(1, $report->query()->count());
        $this->assertSame(30000, $report->summary()['Total']['operasional']);
        $page = Livewire::actingAs($user)->test(self::COMPONENT);
        $this->assertTrue($page->get('shiftOptions')->has('legacy'));
        $page->call('setDateRange', '2026-10-01', '2026-10-01')
            ->set('source', 'Minuman')->set('kind', 'Operasional')->set('shift', 'legacy');
        $this->assertSame($report->summary(), $page->get('summary'));
        $this->assertSame(1, $page->get('rows')->total());
        $page->call('setDateRange', '2026-09-30', '2026-10-01');
        $this->assertSame(50000, $page->get('summary')['Total']['operasional']);
        $page->call('setDateRange', '2026-10-01', '2026-10-31');
        $this->assertSame(70000, $page->get('summary')['Total']['operasional']);
        $page->call('setDateRange', '2026-09-28', '2026-10-04');
        $this->assertSame(90000, $page->get('summary')['Total']['operasional']);
    }

    public function test_pagination_keeps_whole_report_totals_and_resets_when_filters_change(): void
    {
        $user = $this->fixture();
        for ($i = 0; $i < 30; $i++) {
            $this->sale('operasional', 1000);
        }
        $page = Livewire::actingAs($user)->test(self::COMPONENT);
        $this->assertSame(34, $page->get('rows')->total());
        $this->assertCount(25, $page->get('rows')->items());
        $summary = $page->get('summary');
        $page->call('setPage', 2);
        $this->assertCount(9, $page->get('rows')->items());
        $this->assertSame($summary, $page->get('summary'));
        $page->set('kind', 'Pengeluaran')->assertSet('paginators.page', 1);
        $this->assertSame(2, $page->get('rows')->total());
    }

    public function test_invalid_filters_and_dates_cannot_render_or_export_report(): void
    {
        $user = $this->fixture();
        Livewire::actingAs($user)->test(self::COMPONENT)
            ->call('setDateRange', '2026-10-01', '2026-09-30')->assertHasErrors(['dateEnd'])
            ->call('exportExcel')->assertHasErrors(['dateEnd'])->assertSet('report', null);
        foreach (['source' => 'invalid', 'kind' => 'invalid', 'shift' => 'invalid', 'dateStart' => 'not-a-date'] as $field => $value) {
            Livewire::actingAs($user)->test(self::COMPONENT)->set($field, $value)
                ->assertHasErrors([$field])->call('exportExcel')->assertHasErrors([$field]);
        }
    }

    public function test_excel_contains_numeric_totals_and_all_filtered_rows_with_literal_text(): void
    {
        $this->fixture();
        for ($i = 0; $i < 30; $i++) {
            $this->sale('operasional', 1000)->update(['nama_produk' => '=1+1']);
        }
        $report = new OperationalExpenseReport('2026-10-01', '2026-10-01', 'Minuman', 'Operasional');
        $path = tempnam(sys_get_temp_dir(), 'report');
        try {
            file_put_contents($path, Excel::raw(new OperationalExpenseExport($report), \Maatwebsite\Excel\Excel::XLSX));
            Cell::setValueBinder(new DefaultValueBinder);
            $workbook = IOFactory::load($path);
            $summary = $workbook->getSheetByName('Ringkasan');
            $details = $workbook->getSheetByName('Rincian');
            $this->assertSame(40000, $summary->getCell('B8')->getValue());
            $this->assertSame(0, $summary->getCell('C8')->getValue());
            $this->assertSame('Total Operasional dan Pengeluaran', $summary->getCell('A9')->getValue());
            $this->assertSame(40000, $summary->getCell('B9')->getValue());
            $this->assertSame(36, $details->getHighestDataRow());
            $this->assertSame('Minuman', $details->getCell('B3')->getValue());
            $this->assertSame('Operasional', $details->getCell('D3')->getValue());
            $this->assertSame('=1+1', $details->getCell('E6')->getValue());
            $this->assertSame('s', $details->getCell('E6')->getDataType());
            $this->assertSame('n', $details->getCell('H6')->getDataType());
            $this->assertStringContainsString('Rp', $details->getCell('H6')->getStyle()->getNumberFormat()->getFormatCode());
            $workbook->disconnectWorksheets();
        } finally {
            unlink($path);
        }
    }

    public function test_excel_download_is_available_to_both_roles_and_rechecks_access_on_updates(): void
    {
        foreach (['admin', 'kasir_gym'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            Livewire::actingAs($user)->test(self::COMPONENT)->call('exportExcel')
                ->assertFileDownloaded('operasional_pengeluaran_'.now()->format('Y-m-d_His').'.xlsx');
        }
        $user = User::factory()->create(['role' => 'admin']);
        $page = Livewire::actingAs($user)->test(self::COMPONENT);
        $user->update(['role' => 'member']);
        $page->call('exportExcel')->assertForbidden();
    }

    private function fixture(): User
    {
        $user = User::factory()->create(['role' => 'admin']);
        MembershipTransaction::create(['invoice_number' => 'INV-OP', 'user_id' => $user->id, 'admin_id' => $user->id, 'transaction_type' => 'Baru', 'package_name' => 'Gym Internal', 'amount' => 10000, 'payment_method' => 'operasional', 'payment_date' => today(), 'shift' => 'pagi']);
        Expense::create(['admin_id' => $user->id, 'description' => 'Kebersihan', 'amount' => 10000, 'expense_date' => today(), 'shift' => 'pagi']);
        $this->sale('operasional', 10000);
        $this->sale('pengeluaran_umum', 10000);

        return $user;
    }

    private function sale(string $method, int $amount, string $date = '2026-10-01 12:00:00', string $shift = 'pagi'): BeverageSale
    {
        return BeverageSale::create(['nama_produk' => 'Minuman Internal', 'nama_staff' => 'Staff Lama', 'waktu_transaksi' => $date, 'shift' => $shift, 'jumlah_beli' => 1, 'harga_satuan' => $amount, 'total_harga' => $amount, 'keterangan_bayar' => $method]);
    }
}
