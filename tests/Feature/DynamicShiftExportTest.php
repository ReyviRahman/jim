<?php

namespace Tests\Feature;

use App\Exports\BeverageSaleExport;
use App\Exports\BeverageSaleExportDetail;
use App\Exports\BeverageStockCombinedSheet;
use App\Exports\PenjualanExport;
use App\Models\Beverage;
use App\Models\BeverageSale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Sheet;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Tests\TestCase;

class DynamicShiftExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_summary_includes_arbitrary_shifts_and_combines_case_variants(): void
    {
        $this->createSale('pagi');
        $this->createSale('Middle');
        $this->createSale('middle');
        $sheet = $this->render(new BeverageSaleExport('', '2026-09-10'));

        $this->assertSame('SHIFT PAGI: Kasir', $sheet->getCell('A1')->getValue());
        $this->assertSame('SHIFT MIDDLE: Kasir', $sheet->getCell('C1')->getValue());
        $this->assertEquals(10000, $sheet->getCell('D3')->getValue());
        $this->assertEquals(15000, $sheet->getCell('D13')->getValue());
    }

    public function test_summary_supports_more_than_thirteen_shifts(): void
    {
        foreach (range(1, 14) as $index) {
            $this->createSale('Shift '.$index);
        }

        $sheet = $this->render(new BeverageSaleExport('', '2026-09-10'));

        $this->assertSame('SHIFT SHIFT 14: Kasir', $sheet->getCell('AA1')->getValue());
        $this->assertEquals(5000, $sheet->getCell('AB3')->getValue());
        $this->assertEquals(70000, $sheet->getCell('AB13')->getValue());
    }

    public function test_beverage_exports_apply_selected_shift(): void
    {
        $this->createSale('pagi');
        $middle = $this->createSale('Middle');
        $sheet = $this->render(new BeverageSaleExport('', '2026-09-10', null, 'middle'));

        $this->assertSame('SHIFT MIDDLE: Kasir', $sheet->getCell('A1')->getValue());
        $this->assertEquals(5000, $sheet->getCell('B13')->getValue());

        $detail = new BeverageSaleExportDetail('', '2026-09-10', null, 'middle');
        $this->assertSame([$middle->id], $detail->query()->pluck('id')->all());
    }

    public function test_combined_report_lists_every_shift_worked_by_staff(): void
    {
        $this->createSale('pagi');
        $this->createSale('Middle');
        $sheet = $this->render(new BeverageStockCombinedSheet('2026-09-10', '2026-09-10', null));

        $this->assertSame('KASIR (PAGI, MIDDLE)', $sheet->getCell('A3')->getValue());
        $this->assertEquals(10000, $sheet->getCell('B5')->getValue());
    }

    public function test_membership_heading_distinguishes_all_shifts_from_a_shift_named_all(): void
    {
        $all = $this->render(new PenjualanExport(collect(), [], '2026-09-10', '2026-09-10', ''));
        $named = $this->render(new PenjualanExport(collect(), [], '2026-09-10', '2026-09-10', 'all'));

        $this->assertStringContainsString('SEMUA SHIFT', $all->getCell('A1')->getValue());
        $this->assertStringContainsString('ADMIN ALL -', $named->getCell('A1')->getValue());
    }

    private function render(WithEvents $export): Worksheet
    {
        $sheet = (new Spreadsheet)->getActiveSheet();
        $export->registerEvents()[AfterSheet::class](new AfterSheet(new Sheet($sheet), $export));

        return $sheet;
    }

    private function createSale(string $shift): BeverageSale
    {
        $beverage = Beverage::factory()->create();

        return BeverageSale::create([
            'beverage_id' => $beverage->id,
            'nama_produk' => $beverage->nama_produk,
            'nama_staff' => 'Kasir',
            'waktu_transaksi' => '2026-09-10 12:00:00',
            'shift' => $shift,
            'jumlah_beli' => 1,
            'harga_satuan' => 5000,
            'total_harga' => 5000,
            'keterangan_bayar' => 'cash',
        ]);
    }
}
