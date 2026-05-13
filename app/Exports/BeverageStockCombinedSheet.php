<?php

namespace App\Exports;

use App\Models\Beverage;
use App\Models\BeverageRestock;
use App\Models\BeverageSale;
use App\Models\BeverageStokSnapshot;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class BeverageStockCombinedSheet implements WithEvents, WithTitle
{
    private string $startDate;

    private string $endDate;

    private ?string $search;

    private bool $isAdmin;

    private array $keteranganBayarList = [
        'cash',
        'tf_bca_qris',
        'operasional',
        'pengeluaran_umum',
        'deposit_hutang_cash',
        'deposit_hutang_qris',
        'hutang',
    ];

    private array $keteranganBayarLabels = [
        'cash' => 'Cash',
        'tf_bca_qris' => 'TF BCA/QRIS',
        'operasional' => 'Operasional',
        'pengeluaran_umum' => 'Pengeluaran Umum',
        'deposit_hutang_cash' => 'Deposit/Cash',
        'deposit_hutang_qris' => 'Deposit/QRIS',
        'hutang' => 'Hutang',
    ];

    public function __construct(string $startDate, string $endDate, ?string $search, bool $isAdmin = false)
    {
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->search = $search;
        $this->isAdmin = $isAdmin;
    }

    public function title(): string
    {
        return date('d-m-Y', strtotime($this->startDate)).'-sampai-'.date('d-m-Y', strtotime($this->endDate));
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $formattedStart = date('d/m/Y', strtotime($this->startDate));
                $formattedEnd = date('d/m/Y', strtotime($this->endDate));

                // === SECTION 1: TITLE ===
                $titleRow = 1;
                $sheet->setCellValue('A'.$titleRow, 'LAPORAN HASIL PENJUALAN '.$formattedStart.' SAMPAI '.$formattedEnd);
                $sheet->mergeCells('A1:J1');
                $sheet->getStyle('A1:J1')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => '000000']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);

                // === SECTION 2: STAFF SUMMARIES (AGGREGATE) ===
                $groupedData = [];
                $cashPerStaff = [];
                $totalsPerKb = [];
                $allStaff = [];
                $staffShifts = [];

                foreach ($this->keteranganBayarList as $kb) {
                    $totalsPerKb[$kb] = 0;
                }

                $query = BeverageSale::query()
                    ->whereBetween('waktu_transaksi', [$this->startDate.' 00:00:00', $this->endDate.' 23:59:59']);

                if (! empty($this->search)) {
                    $query->whereHas('beverage', function ($q) {
                        $q->where('nama_produk', 'like', '%'.$this->search.'%');
                    });
                }

                $sales = $query->get();

                foreach ($sales as $sale) {
                    $staffName = $sale->nama_staff;
                    $kb = $sale->keterangan_bayar;
                    $shift = $sale->shift;

                    if (! isset($groupedData[$staffName])) {
                        $groupedData[$staffName] = [];
                        foreach ($this->keteranganBayarList as $k) {
                            $groupedData[$staffName][$k] = 0;
                        }
                        $cashPerStaff[$staffName] = 0;
                    }

                    if (isset($groupedData[$staffName][$kb])) {
                        $groupedData[$staffName][$kb] += $sale->total_harga;
                    }

                    if ($kb === 'cash') {
                        $cashPerStaff[$staffName] += $sale->total_harga;
                    }

                    if (! in_array($staffName, $allStaff)) {
                        $allStaff[] = $staffName;
                        $staffShifts[$staffName] = $shift;
                    }

                    if (isset($totalsPerKb[$kb])) {
                        $totalsPerKb[$kb] += $sale->total_harga;
                    }
                }

                $numStaff = count($allStaff);
                $totalCashRow = null;

                if ($numStaff > 0) {
                    $labelWidth = 25;
                    $valueWidth = 15;
                    $headerRow = 3;
                    $subHeaderRow = 4;
                    $dataStartRow = 5;
                    $totalCashRow = $dataStartRow + count($this->keteranganBayarList) - 1;

                    $firstStaffCol = 1;
                    $lastStaffCol = $numStaff * 2;

                    for ($col = $firstStaffCol; $col <= $lastStaffCol; $col++) {
                        $colLetter = Coordinate::stringFromColumnIndex($col);
                        $sheet->getColumnDimension($colLetter)->setWidth($col % 2 === 1 ? $labelWidth : $valueWidth);
                    }

                    foreach ($allStaff as $index => $staffName) {
                        $startCol = $index * 2 + 1;
                        $labelCol = $startCol;
                        $valueCol = $startCol + 1;

                        $labelColLetter = Coordinate::stringFromColumnIndex($labelCol);
                        $valueColLetter = Coordinate::stringFromColumnIndex($valueCol);

                        $staffShift = isset($staffShifts[$staffName]) ? strtoupper($staffShifts[$staffName]) : '';
                        $sheet->setCellValue($labelColLetter.$headerRow, strtoupper($staffName).' ('.$staffShift.')');
                        $sheet->setCellValue($valueColLetter.$headerRow, '');
                        $sheet->setCellValue($labelColLetter.$subHeaderRow, 'Keterangan Bayar');
                        $sheet->setCellValue($valueColLetter.$subHeaderRow, 'Total');

                        $currentRow = $dataStartRow;
                        foreach ($this->keteranganBayarList as $kb) {
                            $sheet->setCellValue($labelColLetter.$currentRow, $this->keteranganBayarLabels[$kb]);
                            $sheet->setCellValue($valueColLetter.$currentRow, $groupedData[$staffName][$kb]);
                            $currentRow++;
                        }

                        // Styling per staff
                        $sheet->getStyle($labelColLetter.$headerRow.':'.$valueColLetter.$headerRow)->applyFromArray([
                            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF22C55E']],
                            'font' => ['bold' => true, 'color' => ['argb' => 'FF000000']],
                            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                        ]);

                        $sheet->getStyle($labelColLetter.$subHeaderRow.':'.$valueColLetter.$subHeaderRow)->applyFromArray([
                            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFE5E7EB']],
                            'font' => ['bold' => true, 'color' => ['argb' => 'FF000000']],
                            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                        ]);

                        for ($row = $dataStartRow; $row < $dataStartRow + count($this->keteranganBayarList); $row++) {
                            $sheet->getStyle($labelColLetter.$row.':'.$valueColLetter.$row)->applyFromArray([
                                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                                'font' => ['color' => ['argb' => 'FF000000']],
                            ]);
                            if ($row % 2 === 0) {
                                $sheet->getStyle($labelColLetter.$row.':'.$valueColLetter.$row)->applyFromArray([
                                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFF9FAFB']],
                                ]);
                            }
                        }

                        // Currency format
                        $currencyFormat = '_("Rp"* #,##0_);_("Rp"* \(#,##0\);_("Rp"* "-"??_);_(@_)';
                        for ($row = $dataStartRow; $row <= $totalCashRow; $row++) {
                            $sheet->getStyle($valueColLetter.$row)->getNumberFormat()->setFormatCode($currencyFormat);
                        }
                    }
                }

                // === SECTION 3: GRAND TOTAL (AGGREGATE) ===
                $grandTotalHeaderRow = $numStaff > 0 ? $totalCashRow + 3 : 3;
                $grandTotalSubHeaderRow = $grandTotalHeaderRow + 1;
                $grandTotalDataStartRow = $grandTotalHeaderRow + 2;
                $grandTotalCashRow = $grandTotalDataStartRow + count($this->keteranganBayarList) - 1;

                $sheet->setCellValue('A'.$grandTotalHeaderRow, 'GRAND TOTAL');
                $sheet->mergeCells('A'.$grandTotalHeaderRow.':B'.$grandTotalHeaderRow);
                $sheet->setCellValue('A'.$grandTotalSubHeaderRow, 'Keterangan Bayar');
                $sheet->setCellValue('B'.$grandTotalSubHeaderRow, 'Total');

                $currentGrandRow = $grandTotalDataStartRow;
                foreach ($this->keteranganBayarList as $kb) {
                    $sheet->setCellValue('A'.$currentGrandRow, $this->keteranganBayarLabels[$kb]);
                    $sheet->setCellValue('B'.$currentGrandRow, $totalsPerKb[$kb]);
                    $currentGrandRow++;
                }

                // Style grand total header
                $sheet->getStyle('A'.$grandTotalHeaderRow.':B'.$grandTotalHeaderRow)->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFF97316']],
                    'font' => ['bold' => true, 'size' => 12, 'color' => ['argb' => 'FF000000']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                ]);

                // Style grand total sub header
                $sheet->getStyle('A'.$grandTotalSubHeaderRow.':B'.$grandTotalSubHeaderRow)->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFE5E7EB']],
                    'font' => ['bold' => true, 'color' => ['argb' => 'FF000000']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                ]);

                // Style grand total data rows
                for ($row = $grandTotalDataStartRow; $row < $grandTotalDataStartRow + count($this->keteranganBayarList); $row++) {
                    $sheet->getStyle('A'.$row.':B'.$row)->applyFromArray([
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                        'font' => ['color' => ['argb' => 'FF000000']],
                    ]);
                    if ($row % 2 === 0) {
                        $sheet->getStyle('A'.$row.':B'.$row)->applyFromArray([
                            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFF9FAFB']],
                        ]);
                    }
                }

                // Currency format for grand total
                $currencyFormat = '_("Rp"* #,##0_);_("Rp"* \(#,##0\);_("Rp"* "-"??_);_(@_)';
                for ($row = $grandTotalDataStartRow; $row <= $grandTotalCashRow; $row++) {
                    $sheet->getStyle('B'.$row)->getNumberFormat()->setFormatCode($currencyFormat);
                }

                // === SECTION 3B: CASH PER STAFF (AGGREGATE) ===
                $cashStaffLabelCol = 'D';
                $cashStaffValueCol = 'E';

                // Header
                $sheet->setCellValue($cashStaffLabelCol.$grandTotalHeaderRow, 'TOTAL CASH');
                $sheet->mergeCells($cashStaffLabelCol.$grandTotalHeaderRow.':'.$cashStaffValueCol.$grandTotalHeaderRow);
                $sheet->setCellValue($cashStaffLabelCol.$grandTotalSubHeaderRow, 'Staff');
                $sheet->setCellValue($cashStaffValueCol.$grandTotalSubHeaderRow, 'Total');

                // Data rows
                $cashStaffCurrentRow = $grandTotalDataStartRow;
                foreach ($cashPerStaff as $staffName => $amount) {
                    $sheet->setCellValue($cashStaffLabelCol.$cashStaffCurrentRow, 'CASH '.strtoupper($staffName));
                    $sheet->setCellValue($cashStaffValueCol.$cashStaffCurrentRow, $amount);
                    $cashStaffCurrentRow++;
                }

                // Grand Total Cash row
                $sheet->setCellValue($cashStaffLabelCol.$cashStaffCurrentRow, 'GRAND TOTAL (UANG REAL)');
                $sheet->setCellValue($cashStaffValueCol.$cashStaffCurrentRow, $totalsPerKb['cash']);
                $lastCashStaffRow = $cashStaffCurrentRow;

                // Style cash staff header
                $sheet->getStyle($cashStaffLabelCol.$grandTotalHeaderRow.':'.$cashStaffValueCol.$grandTotalHeaderRow)->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFF97316']],
                    'font' => ['bold' => true, 'size' => 12, 'color' => ['argb' => 'FF000000']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                ]);

                // Style cash staff sub header
                $sheet->getStyle($cashStaffLabelCol.$grandTotalSubHeaderRow.':'.$cashStaffValueCol.$grandTotalSubHeaderRow)->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFE5E7EB']],
                    'font' => ['bold' => true, 'color' => ['argb' => 'FF000000']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                ]);

                // Style cash staff data rows
                for ($row = $grandTotalDataStartRow; $row < $lastCashStaffRow; $row++) {
                    $sheet->getStyle($cashStaffLabelCol.$row.':'.$cashStaffValueCol.$row)->applyFromArray([
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                        'font' => ['color' => ['argb' => 'FF000000']],
                    ]);
                    if ($row % 2 === 0) {
                        $sheet->getStyle($cashStaffLabelCol.$row.':'.$cashStaffValueCol.$row)->applyFromArray([
                            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFF9FAFB']],
                        ]);
                    }
                }

                // Style grand total cash row
                $sheet->getStyle($cashStaffLabelCol.$lastCashStaffRow.':'.$cashStaffValueCol.$lastCashStaffRow)->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF16A34A']],
                    'font' => ['bold' => true, 'size' => 11, 'color' => ['argb' => 'FF000000']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                ]);

                // Currency format for cash staff
                for ($row = $grandTotalDataStartRow; $row <= $lastCashStaffRow; $row++) {
                    $sheet->getStyle($cashStaffValueCol.$row)->getNumberFormat()->setFormatCode($currencyFormat);
                }

                // Set column widths for grand total section
                $sheet->getColumnDimension('A')->setWidth(25);
                $sheet->getColumnDimension('B')->setWidth(15);

                // === SECTION 4: STOCK DETAIL TABLES PER DATE ===
                $beverages = Beverage::withTrashed()
                    ->when($this->search, fn ($q) => $q->where('nama_produk', 'like', '%'.$this->search.'%'))
                    ->orderBy('nama_produk')
                    ->get();

                $headers = ['Nama Produk', 'Harga Jual', 'Stok Awal', 'Ditambahkan', 'Jumlah Stok', 'Terjual', 'Total Penjualan', 'Stok Akhir'];
                if ($this->isAdmin) {
                    array_splice($headers, 1, 0, 'Harga Modal');
                }

                $stockSectionStartRow = max($grandTotalCashRow + 1, $lastCashStaffRow) + 3;
                $currentRow = $stockSectionStartRow;

                $current = strtotime($this->startDate);
                $end = strtotime($this->endDate);

                $isFirstTable = true;

                while ($current <= $end) {
                    $date = date('Y-m-d', $current);
                    $formattedDate = date('d/m/Y', $current);

                    if (! $isFirstTable) {
                        $currentRow += 1; // 1 baris kosong antar tabel
                    }
                    $isFirstTable = false;

                    // Title tabel per tanggal
                    $tableTitleRow = $currentRow;
                    $sheet->setCellValue('A'.$tableTitleRow, 'STOCK DETAIL - '.$formattedDate);
                    $lastCol = count($headers);
                    $lastColLetter = Coordinate::stringFromColumnIndex($lastCol);
                    $sheet->mergeCells('A'.$tableTitleRow.':'.$lastColLetter.$tableTitleRow);
                    $sheet->getStyle('A'.$tableTitleRow.':'.$lastColLetter.$tableTitleRow)->applyFromArray([
                        'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => '000000']],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFDBEAFE']],
                    ]);

                    // Header kolom
                    $headerRow = $currentRow + 1;
                    foreach ($headers as $index => $header) {
                        $col = $index + 1;
                        $colLetter = Coordinate::stringFromColumnIndex($col);
                        $sheet->setCellValue($colLetter.$headerRow, $header);
                    }
                    $sheet->getStyle("A{$headerRow}:{$lastColLetter}{$headerRow}")->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['argb' => 'FF000000']],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                    ]);

                    // Data rows
                    $dataStartRow = $headerRow + 1;
                    $rowCursor = $dataStartRow;
                    foreach ($beverages as $beverage) {
                        $stokAwal = BeverageStokSnapshot::where('beverage_id', $beverage->id)
                            ->where('tipe', 'init')
                            ->whereDate('tanggal', $date)
                            ->sum('jumlah');

                        $ditambahkan = BeverageRestock::where('beverage_id', $beverage->id)
                            ->where('tipe', 'restock')
                            ->whereDate('tanggal', $date)
                            ->sum('jumlah_tambah');

                        $terjual = BeverageSale::where('beverage_id', $beverage->id)
                            ->whereNotIn('keterangan_bayar', ['deposit_hutang_cash', 'deposit_hutang_qris'])
                            ->whereDate('waktu_transaksi', $date)
                            ->sum('jumlah_beli');

                        $jumlahStok = $stokAwal + $ditambahkan;
                        $totalPenjualan = $terjual * $beverage->harga_jual;

                        $isToday = $date === date('Y-m-d');
                        if ($isToday) {
                            $stokAkhir = $beverage->stok_sekarang;
                        } else {
                            $stokAkhir = BeverageStokSnapshot::where('beverage_id', $beverage->id)
                                ->where('tipe', 'last')
                                ->whereDate('tanggal', $date)
                                ->sum('jumlah');
                        }

                        $sheet->setCellValue('A'.$rowCursor, $beverage->nama_produk.($beverage->trashed() ? ' (Dihapus)' : ''));
                        $col = 2;
                        if ($this->isAdmin) {
                            $sheet->setCellValue(Coordinate::stringFromColumnIndex($col).$rowCursor, $beverage->harga_modal);
                            $col++;
                        }
                        $sheet->setCellValue(Coordinate::stringFromColumnIndex($col).$rowCursor, $beverage->harga_jual);
                        $col++;
                        $sheet->setCellValue(Coordinate::stringFromColumnIndex($col).$rowCursor, $stokAwal);
                        $col++;
                        $sheet->setCellValue(Coordinate::stringFromColumnIndex($col).$rowCursor, $ditambahkan);
                        $col++;
                        $sheet->setCellValue(Coordinate::stringFromColumnIndex($col).$rowCursor, $jumlahStok);
                        $col++;
                        $sheet->setCellValue(Coordinate::stringFromColumnIndex($col).$rowCursor, $terjual);
                        $col++;
                        $sheet->setCellValue(Coordinate::stringFromColumnIndex($col).$rowCursor, $totalPenjualan);
                        $col++;
                        $sheet->setCellValue(Coordinate::stringFromColumnIndex($col).$rowCursor, $stokAkhir);

                        $rowCursor++;
                    }

                    $lastDataRow = $rowCursor - 1;

                    // Style stock data rows
                    if ($lastDataRow >= $dataStartRow) {
                        $sheet->getStyle("A{$dataStartRow}:{$lastColLetter}{$lastDataRow}")->applyFromArray([
                            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                            'font' => ['color' => ['argb' => 'FF000000']],
                        ]);

                        for ($row = $dataStartRow; $row <= $lastDataRow; $row++) {
                            if ($row % 2 === 0) {
                                $sheet->getStyle("A{$row}:{$lastColLetter}{$row}")->applyFromArray([
                                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFF9FAFB']],
                                ]);
                            }
                        }
                    }

                    // Currency format for stock table
                    if ($lastDataRow >= $dataStartRow) {
                        $hargaJualCol = $this->isAdmin ? 3 : 2;
                        $totalPenjualanCol = $this->isAdmin ? 8 : 7;
                        $sheet->getStyle(Coordinate::stringFromColumnIndex($hargaJualCol)."{$dataStartRow}:".Coordinate::stringFromColumnIndex($hargaJualCol).$lastDataRow)->getNumberFormat()->setFormatCode($currencyFormat);
                        $sheet->getStyle(Coordinate::stringFromColumnIndex($totalPenjualanCol)."{$dataStartRow}:".Coordinate::stringFromColumnIndex($totalPenjualanCol).$lastDataRow)->getNumberFormat()->setFormatCode($currencyFormat);
                        if ($this->isAdmin) {
                            $sheet->getStyle("B{$dataStartRow}:B{$lastDataRow}")->getNumberFormat()->setFormatCode($currencyFormat);
                        }
                    }

                    $currentRow = $rowCursor;
                    $current = strtotime('+1 day', $current);
                }

                // Set column widths for stock detail tables
                $sheet->getColumnDimension('A')->setWidth(35);
                $sheet->getColumnDimension('B')->setWidth(15);
                $sheet->getColumnDimension('C')->setWidth(15);
                $sheet->getColumnDimension('D')->setWidth(12);
                $sheet->getColumnDimension('E')->setWidth(14);
                $sheet->getColumnDimension('F')->setWidth(14);
                $sheet->getColumnDimension('G')->setWidth(10);
                $sheet->getColumnDimension('H')->setWidth(18);
                $sheet->getColumnDimension('I')->setWidth(12);
                if (! $this->isAdmin) {
                    $sheet->getColumnDimension('J')->setWidth(12);
                }

                // Set all text to black
                $sheet->getStyle($sheet->calculateWorksheetDimension())->applyFromArray([
                    'font' => ['color' => ['rgb' => '000000']],
                ]);
            },
        ];
    }
}
