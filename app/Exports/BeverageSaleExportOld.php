<?php

namespace App\Exports;

use App\Models\BeverageSale;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class BeverageSaleExport implements FromCollection, ShouldAutoSize, WithEvents
{
    use Exportable;

    public $searchProduct;

    public $start_date;

    public $end_date;

    private $groupedData = [];

    private $cashTotals = [];

    private $grandTotal = 0;

    private $shifts = ['pagi', 'siang', 'malam'];

    private $keteranganBayarList = [
        'cash',
        'tf_bca_qris',
        'operasional',
        'pengeluaran_umum',
        'deposit_hutang_cash',
        'deposit_hutang_qris',
        'hutang',
    ];

    private $keteranganBayarLabels = [
        'cash' => 'Cash',
        'tf_bca_qris' => 'TF BCA/QRIS',
        'operasional' => 'Operasional',
        'pengeluaran_umum' => 'Pengeluaran Umum',
        'deposit_hutang_cash' => 'Deposit/Cash',
        'deposit_hutang_qris' => 'Deposit/QRIS',
        'hutang' => 'Hutang',
    ];

    public function __construct($searchProduct, $start_date, $end_date)
    {
        $this->searchProduct = $searchProduct;
        $this->start_date = $start_date;
        $this->end_date = $end_date;
        $this->prepareData();
    }

    private function prepareData()
    {
        $query = BeverageSale::query()->with(['beverage' => function ($q) {
            $q->withTrashed();
        }]);

        if (! empty($this->searchProduct)) {
            $query->where(function ($q) {
                $q->whereHas('beverage', function ($q2) {
                    $q2->where('nama_produk', 'like', '%'.$this->searchProduct.'%');
                })
                    ->orWhere('nama_staff', 'like', '%'.$this->searchProduct.'%');
            });
        }

        if (! empty($this->start_date)) {
            $query->whereDate('waktu_transaksi', '>=', $this->start_date);
        }

        if (! empty($this->end_date)) {
            $query->whereDate('waktu_transaksi', '<=', $this->end_date);
        }

        $sales = $query->get();

        $this->groupedData = [];
        $this->cashTotals = [];
        $this->grandTotal = 0;

        foreach ($this->shifts as $shift) {
            $this->groupedData[$shift] = [];
            foreach ($this->keteranganBayarList as $kb) {
                $this->groupedData[$shift][$kb] = 0;
            }
            $this->cashTotals[$shift] = 0;
        }

        foreach ($sales as $sale) {
            $shift = $sale->shift;
            $kb = $sale->keterangan_bayar;

            if (isset($this->groupedData[$shift][$kb])) {
                $this->groupedData[$shift][$kb] += $sale->total_harga;
            }

            if ($kb === 'cash') {
                $this->cashTotals[$shift] += $sale->total_harga;
            }

            $this->grandTotal += $sale->total_harga;
        }
    }

    public function collection()
    {
        return collect([]);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $activeShifts = [];

                foreach ($this->shifts as $shift) {
                    $hasData = false;
                    foreach ($this->keteranganBayarList as $kb) {
                        if ($this->groupedData[$shift][$kb] > 0) {
                            $hasData = true;
                            break;
                        }
                    }
                    if ($hasData) {
                        $activeShifts[] = $shift;
                    }
                }

                $numShifts = count($activeShifts);
                if ($numShifts === 0) {
                    return;
                }

                $labelWidth = 20;
                $valueWidth = 15;

                $headerRow = 1;
                $subHeaderRow = 2;
                $dataStartRow = 3;
                $totalCashRow = $dataStartRow + count($this->keteranganBayarList);
                $grandTotalRow = $totalCashRow + 2;

                foreach ($activeShifts as $index => $shift) {
                    $startCol = $index * 2 + 1;
                    $labelCol = $startCol;
                    $valueCol = $startCol + 1;

                    $labelColLetter = Coordinate::stringFromColumnIndex($labelCol);
                    $valueColLetter = Coordinate::stringFromColumnIndex($valueCol);

                    $sheet->setCellValue($labelColLetter.$headerRow, ucfirst($shift));
                    $sheet->setCellValue($labelColLetter.$subHeaderRow, 'Keterangan Bayar');
                    $sheet->setCellValue($valueColLetter.$subHeaderRow, 'Total');

                    $currentRow = $dataStartRow;
                    foreach ($this->keteranganBayarList as $kb) {
                        $sheet->setCellValue($labelColLetter.$currentRow, $this->keteranganBayarLabels[$kb]);
                        $sheet->setCellValue($valueColLetter.$currentRow, $this->groupedData[$shift][$kb]);
                        $currentRow++;
                    }

                    $sheet->setCellValue($labelColLetter.$totalCashRow, 'Total Cash '.ucfirst($shift));
                    $sheet->setCellValue($valueColLetter.$totalCashRow, $this->cashTotals[$shift]);

                    $sheet->getColumnDimension($labelColLetter)->setWidth($labelWidth);
                    $sheet->getColumnDimension($valueColLetter)->setWidth($valueWidth);
                }

                $firstShiftCol = 1;
                $lastShiftCol = $numShifts * 2;
                $firstColLetter = Coordinate::stringFromColumnIndex($firstShiftCol);
                $lastColLetter = Coordinate::stringFromColumnIndex($lastShiftCol);

                for ($col = $firstShiftCol; $col <= $lastShiftCol; $col++) {
                    $colLetter = Coordinate::stringFromColumnIndex($col);
                    $sheet->getColumnDimension($colLetter)->setWidth($col % 2 === 1 ? $labelWidth : $valueWidth);
                }

                $sheet->setCellValue('A'.$grandTotalRow, 'GRAND TOTAL');
                $valueColOfLastShift = Coordinate::stringFromColumnIndex($lastShiftCol);
                $sheet->setCellValue($valueColOfLastShift.$grandTotalRow, $this->grandTotal);

                for ($i = 0; $i < $numShifts; $i++) {
                    $startCol = $i * 2 + 1;
                    $labelCol = $startCol;
                    $valueCol = $startCol + 1;
                    $labelColLetter = Coordinate::stringFromColumnIndex($labelCol);
                    $valueColLetter = Coordinate::stringFromColumnIndex($valueCol);

                    $sheet->getStyle($labelColLetter.$headerRow.':'.$valueColLetter.$headerRow)->applyFromArray([
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF22C55E']],
                        'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                    ]);

                    $sheet->getStyle($labelColLetter.$subHeaderRow.':'.$valueColLetter.$subHeaderRow)->applyFromArray([
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFE5E7EB']],
                        'font' => ['bold' => true],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                    ]);

                    for ($row = $dataStartRow; $row < $dataStartRow + count($this->keteranganBayarList); $row++) {
                        $sheet->getStyle($labelColLetter.$row.':'.$valueColLetter.$row)->applyFromArray([
                            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                        ]);
                        if ($row % 2 === 0) {
                            $sheet->getStyle($labelColLetter.$row.':'.$valueColLetter.$row)->applyFromArray([
                                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFF9FAFB']],
                            ]);
                        }
                    }

                    $sheet->getStyle($labelColLetter.$totalCashRow.':'.$valueColLetter.$totalCashRow)->applyFromArray([
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFFEF08A']],
                        'font' => ['bold' => true],
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                    ]);
                }

                $sheet->getStyle('A'.$grandTotalRow.':'.$valueColOfLastShift.$grandTotalRow)->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF16A34A']],
                    'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                ]);

                for ($col = $firstShiftCol; $col <= $lastShiftCol; $col++) {
                    $colLetter = Coordinate::stringFromColumnIndex($col);
                    for ($row = $headerRow; $row <= $grandTotalRow; $row++) {
                        $sheet->getStyle($colLetter.$row)->applyFromArray([
                            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                        ]);
                    }
                }

                for ($i = 0; $i < $numShifts; $i++) {
                    $valueCol = ($i * 2 + 1) + 1;
                    $valueColLetter = Coordinate::stringFromColumnIndex($valueCol);

                    for ($row = $dataStartRow; $row <= $totalCashRow; $row++) {
                        $sheet->getStyle($valueColLetter.$row)->getNumberFormat()->setFormatCode('_("Rp"* #,##0_);_("Rp"* \(#,##0\);_("Rp"* "-"??_);_(@_)');
                    }
                }

                $sheet->getStyle($valueColOfLastShift.$grandTotalRow)->getNumberFormat()->setFormatCode('_("Rp"* #,##0_);_("Rp"* \(#,##0\);_("Rp"* "-"??_);_(@_)');
            },
        ];
    }
}
