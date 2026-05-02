<?php

namespace App\Exports;

use App\Models\Beverage;
use App\Models\BeverageRestock;
use App\Models\BeverageSale;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class BeverageSaleExportDetail implements FromQuery, ShouldAutoSize, WithEvents, WithHeadings, WithMapping, WithStyles
{
    use Exportable;

    public $searchProduct;

    public $start_date;

    public $end_date;

    private $rowNumber = 0;

    private $grandTotal = 0;

    private $totalItem = 0;

    public function __construct($searchProduct, $start_date, $end_date)
    {
        $this->searchProduct = $searchProduct;
        $this->start_date = $start_date;
        $this->end_date = $end_date;
    }

    public function query()
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

        return $query->latest();
    }

    public function headings(): array
    {
        return [
            'No',
            'Tanggal',
            'Staff',
            'Nama Pelanggan',
            'Produk',
            'Jumlah',
            'Harga Satuan',
            'Total',
            'Shift',
            'Metode Bayar',
        ];
    }

    public function map($sale): array
    {
        $this->grandTotal += $sale->total_harga;
        $this->totalItem += $sale->jumlah_beli;

        $metode = [
            'cash' => 'Cash',
            'tf_bca_qris' => 'TF BCA/Qris',
            'operasional' => 'Operasional',
            'pengeluaran_umum' => 'Pengeluaran Umum',
            'deposit_hutang_cash' => 'Deposit/Cash',
            'deposit_hutang_qris' => 'Deposit/QRIS',
            'hutang' => 'Hutang',
        ];

        return [
            ++$this->rowNumber,
            $sale->waktu_transaksi->format('d M Y H:i'),
            $sale->nama_staff,
            $sale->nama_penghutang ?? '-',
            $sale->nama_produk ?? '-',
            $sale->jumlah_beli,
            $sale->harga_satuan,
            $sale->total_harga,
            ucfirst($sale->shift),
            $metode[$sale->keterangan_bayar] ?? $sale->keterangan_bayar,
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // Insert title rows at top
                $sheet->insertNewRowBefore(1, 2);

                $startDateFormatted = $this->start_date ? date('d M Y', strtotime($this->start_date)) : '-';
                $endDateFormatted = $this->end_date ? date('d M Y', strtotime($this->end_date)) : '-';

                $sheet->setCellValue('A1', 'LAPORAN PENJUALAN MINUMAN');
                $sheet->mergeCells('A1:J1');
                $sheet->getStyle('A1:J1')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 14, 'color' => ['argb' => 'FF000000']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);

                $sheet->setCellValue('A2', 'Periode: '.$startDateFormatted.' s/d '.$endDateFormatted);
                $sheet->mergeCells('A2:J2');
                $sheet->getStyle('A2:J2')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 11, 'color' => ['argb' => 'FF000000']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);

                // Style heading row (now at row 3)
                $sheet->getStyle('A3:J3')->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['argb' => 'FF000000']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF22C55E']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                ]);

                $lastRow = $sheet->getHighestRow() + 1;

                $sheet->setCellValue('A'.$lastRow, 'GRAND TOTAL');
                $sheet->setCellValue('F'.$lastRow, $this->totalItem);
                $sheet->setCellValue('H'.$lastRow, $this->grandTotal);

                $sheet->getStyle('A'.$lastRow.':J'.$lastRow)->applyFromArray([
                    'font' => ['bold' => true],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF16A34A']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => Color::COLOR_BLACK]]],
                ]);

                $sheet->getStyle('F'.$lastRow)->applyFromArray([
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);

                $currencyFormat = '_("Rp"* #,##0_);_("Rp"* \(#,##0\);_("Rp"* "-"??_);_(@_)';
                $sheet->getStyle('H'.$lastRow)->getNumberFormat()->setFormatCode($currencyFormat);

                $sheet->getStyle('G4:G'.($lastRow - 1))->getNumberFormat()->setFormatCode($currencyFormat);
                $sheet->getStyle('H4:H'.($lastRow - 1))->getNumberFormat()->setFormatCode($currencyFormat);

                // === SECTION: STOCK DETAIL TABLE ===
                $stockHeaderRow = $lastRow + 2;
                $stockDataStartRow = $stockHeaderRow + 1;

                $beverages = Beverage::withTrashed()
                    ->when($this->searchProduct, fn ($q) => $q->where('nama_produk', 'like', '%'.$this->searchProduct.'%'))
                    ->orderBy('nama_produk')
                    ->get();

                $headers = ['Produk', 'Harga Modal', 'Harga Jual', 'Stok Awal', 'Ditambah', 'Jumlah Stok', 'Terjual', 'Jumlah Jual', 'Stok Akhir'];

                foreach ($headers as $index => $header) {
                    $col = $index + 1;
                    $colLetter = Coordinate::stringFromColumnIndex($col);
                    $sheet->setCellValue($colLetter.$stockHeaderRow, $header);
                }

                $currentRow = $stockDataStartRow;
                foreach ($beverages as $beverage) {
                    $terjual = BeverageSale::where('beverage_id', $beverage->id)
                        ->whereNotIn('keterangan_bayar', ['deposit_hutang_cash', 'deposit_hutang_qris', 'operasional', 'pengeluaran_umum'])
                        ->when($this->start_date, function ($query) {
                            $query->whereDate('waktu_transaksi', '>=', $this->start_date);
                        })
                        ->when($this->end_date, function ($query) {
                            $query->whereDate('waktu_transaksi', '<=', $this->end_date);
                        })
                        ->sum('jumlah_beli');

                    $ditambah = BeverageRestock::where('beverage_id', $beverage->id)
                        ->when($this->start_date, function ($query) {
                            $query->whereDate('tanggal', '>=', $this->start_date);
                        })
                        ->when($this->end_date, function ($query) {
                            $query->whereDate('tanggal', '<=', $this->end_date);
                        })
                        ->sum('jumlah_tambah');

                    $stokAkhir = $beverage->stok_sekarang;
                    $jumlahStok = $stokAkhir + $terjual;
                    $stokAwal = $jumlahStok - $ditambah;
                    $jumlahJual = $terjual * $beverage->harga_jual;

                    $sheet->setCellValue('A'.$currentRow, $beverage->nama_produk.($beverage->trashed() ? ' (Dihapus)' : ''));
                    $sheet->setCellValue('B'.$currentRow, $beverage->harga_modal);
                    $sheet->setCellValue('C'.$currentRow, $beverage->harga_jual);
                    $sheet->setCellValue('D'.$currentRow, $stokAwal);
                    $sheet->setCellValue('E'.$currentRow, $ditambah);
                    $sheet->setCellValue('F'.$currentRow, $jumlahStok);
                    $sheet->setCellValue('G'.$currentRow, $terjual);
                    $sheet->setCellValue('H'.$currentRow, $jumlahJual);
                    $sheet->setCellValue('I'.$currentRow, $stokAkhir);

                    $currentRow++;
                }

                $lastDataRow = $currentRow - 1;

                $sheet->getStyle("A{$stockHeaderRow}:I{$stockHeaderRow}")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['argb' => 'FF000000']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF22C55E']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                ]);

                if ($lastDataRow >= $stockDataStartRow) {
                    $sheet->getStyle("A{$stockDataStartRow}:I{$lastDataRow}")->applyFromArray([
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                        'font' => ['color' => ['argb' => 'FF000000']],
                    ]);

                    for ($row = $stockDataStartRow; $row <= $lastDataRow; $row++) {
                        if ($row % 2 === 0) {
                            $sheet->getStyle("A{$row}:I{$row}")->applyFromArray([
                                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFF9FAFB']],
                            ]);
                        }
                    }
                }

                $currencyFormat = '_("Rp"* #,##0_);_("Rp"* \(#,##0\);_("Rp"* "-"??_);_(@_)';
                if ($lastDataRow >= $stockDataStartRow) {
                    $sheet->getStyle("B{$stockDataStartRow}:B{$lastDataRow}")->getNumberFormat()->setFormatCode($currencyFormat);
                    $sheet->getStyle("C{$stockDataStartRow}:C{$lastDataRow}")->getNumberFormat()->setFormatCode($currencyFormat);
                    $sheet->getStyle("H{$stockDataStartRow}:H{$lastDataRow}")->getNumberFormat()->setFormatCode($currencyFormat);
                }

                // === SECTION: SALES BY PAYMENT METHOD TABLES ===
                $nextTableRow = $lastDataRow + 3;

                $paymentMethodConfigs = [
                    'cash' => ['label' => 'CASH', 'show_customer' => false],
                    'tf_bca_qris' => ['label' => 'TF BCA/QRIS', 'show_customer' => false],
                    'operasional' => ['label' => 'OPERASIONAL', 'show_customer' => false],
                    'pengeluaran_umum' => ['label' => 'PENGELUARAN UMUM', 'show_customer' => false],
                    'deposit_hutang_cash' => ['label' => 'DEPOSIT/CASH', 'show_customer' => true],
                    'deposit_hutang_qris' => ['label' => 'DEPOSIT/QRIS', 'show_customer' => true],
                    'hutang' => ['label' => 'HUTANG', 'show_customer' => true],
                ];

                foreach ($paymentMethodConfigs as $methodKey => $config) {
                    $salesByMethod = BeverageSale::query()
                        ->where('keterangan_bayar', $methodKey)
                        ->when($this->searchProduct, function ($query) {
                            $query->where(function ($q) {
                                $q->whereHas('beverage', function ($q2) {
                                    $q2->where('nama_produk', 'like', '%'.$this->searchProduct.'%');
                                })
                                    ->orWhere('nama_staff', 'like', '%'.$this->searchProduct.'%');
                            });
                        })
                        ->when($this->start_date, function ($query) {
                            $query->whereDate('waktu_transaksi', '>=', $this->start_date);
                        })
                        ->when($this->end_date, function ($query) {
                            $query->whereDate('waktu_transaksi', '<=', $this->end_date);
                        })
                        ->orderBy('waktu_transaksi')
                        ->get();

                    if ($salesByMethod->isEmpty()) {
                        continue;
                    }

                    // Title row
                    $sheet->setCellValue('A'.$nextTableRow, 'METODE PEMBAYARAN: '.$config['label']);
                    $sheet->mergeCells('A'.$nextTableRow.':F'.$nextTableRow);
                    $sheet->getStyle('A'.$nextTableRow.':F'.$nextTableRow)->applyFromArray([
                        'font' => ['bold' => true, 'size' => 12, 'color' => ['argb' => 'FF000000']],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF16A34A']],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                    ]);
                    $nextTableRow++;

                    // Headers
                    $headerRow = $nextTableRow;
                    $sheet->setCellValue('A'.$headerRow, 'TANGGAL');
                    $sheet->setCellValue('B'.$headerRow, 'STAFF');
                    $colIndex = 3;
                    if ($config['show_customer']) {
                        $colLetter = Coordinate::stringFromColumnIndex($colIndex);
                        $sheet->setCellValue($colLetter.$headerRow, 'NAMA PELANGGAN');
                        $colIndex++;
                    }
                    $colLetter = Coordinate::stringFromColumnIndex($colIndex);
                    $sheet->setCellValue($colLetter.$headerRow, 'PRODUK');
                    $colIndex++;
                    $colLetter = Coordinate::stringFromColumnIndex($colIndex);
                    $sheet->setCellValue($colLetter.$headerRow, 'JUMLAH');
                    $colIndex++;
                    $colLetter = Coordinate::stringFromColumnIndex($colIndex);
                    $sheet->setCellValue($colLetter.$headerRow, 'HARGA SATUAN');
                    $colIndex++;
                    $colLetter = Coordinate::stringFromColumnIndex($colIndex);
                    $sheet->setCellValue($colLetter.$headerRow, 'TOTAL');
                    $lastCol = $colIndex;
                    $nextTableRow++;

                    // Style header
                    $lastColLetter = Coordinate::stringFromColumnIndex($lastCol);
                    $sheet->getStyle("A{$headerRow}:{$lastColLetter}{$headerRow}")->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['argb' => 'FF000000']],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF22C55E']],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                    ]);

                    // Data rows
                    $dataStartRow = $nextTableRow;
                    $methodTotal = 0;
                    foreach ($salesByMethod as $sale) {
                        $sheet->setCellValue('A'.$nextTableRow, $sale->waktu_transaksi->format('d M Y H:i'));
                        $sheet->setCellValue('B'.$nextTableRow, $sale->nama_staff);
                        $colIndex = 3;
                        if ($config['show_customer']) {
                            $colLetter = Coordinate::stringFromColumnIndex($colIndex);
                            $sheet->setCellValue($colLetter.$nextTableRow, $sale->nama_penghutang ?? '-');
                            $colIndex++;
                        }
                        $colLetter = Coordinate::stringFromColumnIndex($colIndex);
                        $sheet->setCellValue($colLetter.$nextTableRow, $sale->nama_produk ?? '-');
                        $colIndex++;
                        $colLetter = Coordinate::stringFromColumnIndex($colIndex);
                        $sheet->setCellValue($colLetter.$nextTableRow, $sale->jumlah_beli);
                        $colIndex++;
                        $colLetter = Coordinate::stringFromColumnIndex($colIndex);
                        $sheet->setCellValue($colLetter.$nextTableRow, $sale->harga_satuan);
                        $colIndex++;
                        $colLetter = Coordinate::stringFromColumnIndex($colIndex);
                        $sheet->setCellValue($colLetter.$nextTableRow, $sale->total_harga);
                        $methodTotal += $sale->total_harga;

                        $nextTableRow++;
                    }

                    $lastDataRowMethod = $nextTableRow - 1;

                    // Style data rows
                    if ($lastDataRowMethod >= $dataStartRow) {
                        $sheet->getStyle("A{$dataStartRow}:{$lastColLetter}{$lastDataRowMethod}")->applyFromArray([
                            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                            'font' => ['color' => ['argb' => 'FF000000']],
                        ]);

                        for ($row = $dataStartRow; $row <= $lastDataRowMethod; $row++) {
                            if ($row % 2 === 0) {
                                $sheet->getStyle("A{$row}:{$lastColLetter}{$row}")->applyFromArray([
                                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFF9FAFB']],
                                ]);
                            }
                        }

                        // Currency format
                        $totalCol = $lastCol;
                        $hargaCol = $lastCol - 1;
                        $hargaColLetter = Coordinate::stringFromColumnIndex($hargaCol);
                        $totalColLetter = Coordinate::stringFromColumnIndex($totalCol);
                        $sheet->getStyle("{$hargaColLetter}{$dataStartRow}:{$hargaColLetter}{$lastDataRowMethod}")->getNumberFormat()->setFormatCode($currencyFormat);
                        $sheet->getStyle("{$totalColLetter}{$dataStartRow}:{$totalColLetter}{$lastDataRowMethod}")->getNumberFormat()->setFormatCode($currencyFormat);
                    }

                    // Total row
                    $totalRow = $nextTableRow;
                    $sheet->setCellValue('A'.$totalRow, 'TOTAL '.$config['label']);
                    $totalColLetter = Coordinate::stringFromColumnIndex($lastCol);
                    $sheet->setCellValue($totalColLetter.$totalRow, $methodTotal);
                    $sheet->getStyle("A{$totalRow}:{$totalColLetter}{$totalRow}")->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['argb' => 'FF000000']],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF16A34A']],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                    ]);
                    $sheet->getStyle($totalColLetter.$totalRow)->getNumberFormat()->setFormatCode($currencyFormat);

                    $nextTableRow += 2;
                }
            },
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [];
    }
}
