<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use Carbon\Carbon;

class PenjualanExport implements WithEvents
{
    protected $transactions;
    protected $summaryTotal;
    protected $startDate; 
    protected $endDate;
    protected $shift; 

    public function __construct($transactions, $summaryTotal, $startDate, $endDate, $shift)
    {
        $this->transactions = $transactions;
        $this->summaryTotal = $summaryTotal;
        $this->startDate = $startDate; 
        $this->endDate = $endDate;
        $this->shift = $shift;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                
                // ==========================================
                // 1. PENGATURAN LEBAR KOLOM (A - K) -> Ditambah untuk Catatan & Follow Up
                // ==========================================
                $columns = ['A'=>25, 'B'=>15, 'C'=>20, 'D'=>20, 'E'=>20, 'F'=>25, 'G'=>25, 'H'=>15, 'I'=>15, 'J'=>18, 'K'=>18];
                foreach ($columns as $col => $width) {
                    $sheet->getColumnDimension($col)->setWidth($width);
                }

                // ==========================================
                // 2. JUDUL PALING ATAS (Baris 1)
                // ==========================================
                // 2. JUDUL PALING ATAS (Baris 1)
                $shiftText = strtoupper($this->shift === 'all' ? 'PAGI & SIANG' : $this->shift);

                // Logika Format Tanggal
                if ($this->startDate === $this->endDate) {
                    // Jika cuma 1 hari (misal: 15 Agustus 2026)
                    $tanggalFormat = Carbon::parse($this->startDate)->translatedFormat('d F Y');
                } else {
                    // Jika rentang hari (misal: 01 Agustus 2026 - 15 Agustus 2026)
                    $awal = Carbon::parse($this->startDate)->translatedFormat('d M Y');
                    $akhir = Carbon::parse($this->endDate)->translatedFormat('d M Y');
                    $tanggalFormat = $awal . ' - ' . $akhir;
                }

                $judulBesar = "PENJUALAN ADMIN " . $shiftText . " - " . strtoupper($tanggalFormat);

                $sheet->setCellValue('A1', $judulBesar);
                
                $sheet->setCellValue('A1', $judulBesar);
                $sheet->mergeCells('A1:K1'); // Merge diperlebar sampai K
                $sheet->getStyle('A1:K1')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 14],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                ]);
                $sheet->getRowDimension(1)->setRowHeight(25);

                // ==========================================
                // 3. HEADER TABEL UTAMA (Baris 3) -> Penambahan Catatan & Follow Up
                // ==========================================
                $headers = ['NAMA', 'TANGGAL BAYAR', 'TANGGAL MULAI AKTIF', 'TANGGAL BERAKHIR (MASA AKTIF)', 'STATUS', 'PAKET MEMBER', 'CATATAN', 'NOMINAL', 'METODE BAYAR', 'ADMIN FOLLOW UP', 'SALES FOLLOW UP'];
                foreach (array_values($headers) as $index => $header) {
                    $col = chr(65 + $index);
                    $sheet->setCellValue($col . '3', $header);
                }

                $sheet->getStyle('A3:K3')->applyFromArray([ // Diperlebar sampai K
                    'font' => ['bold' => true, 'color' => ['argb' => Color::COLOR_WHITE]],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1F2937']], 
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => Color::COLOR_BLACK]]]
                ]);
                $sheet->getRowDimension(3)->setRowHeight(30);

                // ==========================================
                // 4. ISI DATA TRANSAKSI (Mulai Baris 4)
                // ==========================================
                $row = 4;
                foreach ($this->transactions as $trx) {
                    $nama = '-';
                    if ($trx->membership && $trx->membership->members->count() > 0) {
                        $nama = $trx->membership->members->pluck('name')->join(', ');
                    } else {
                        $nama = $trx->user->name ?? '-';
                    }

                    $sheet->setCellValue('A'.$row, $nama);
                    $sheet->setCellValue('B'.$row, Carbon::parse($trx->payment_date)->format('d-M-Y'));
                    $sheet->setCellValue('C'.$row, $trx->start_date ? Carbon::parse($trx->start_date)->format('d/m/Y') : 'BELUM AKTIF');
                    $sheet->setCellValue('D'.$row, $trx->end_date ? Carbon::parse($trx->end_date)->format('d/m/Y') : 'BELUM AKTIF');
                    $sheet->setCellValue('E'.$row, strtoupper($trx->transaction_type));
                    $sheet->setCellValue('F'.$row, strtoupper($trx->package_name));
                    $sheet->setCellValue('G'.$row, $trx->notes ?? '-'); // Kolom Catatan Baru
                    $sheet->setCellValue('H'.$row, $trx->amount); // Nominal Geser ke H
                    $sheet->setCellValue('I'.$row, strtoupper($trx->payment_method)); // Geser ke I
                    $sheet->setCellValue('J'.$row, strtoupper($trx->followUp->name ?? '-')); // Geser ke J
                    $sheet->setCellValue('K'.$row, strtoupper($trx->followUpTwo->name ?? '-')); // Kolom Follow Up Baru

                    $row++;
                }

                $lastDataRow = $row - 1;
                if ($lastDataRow >= 4) {
                    $sheet->getStyle('A4:K'.$lastDataRow)->applyFromArray([ // Diperlebar sampai K
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFCCFFFF']], 
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
                    ]);
                    // Kolom nominal berubah dari G menjadi H
                    $sheet->getStyle('H4:H'.$lastDataRow)->getNumberFormat()->setFormatCode('_("Rp"* #,##0_);_("Rp"* \(#,##0\);_("Rp"* "-"??_);_(@_)');
                }

                // ==========================================
                // 5. BARIS TOTAL TRANSAKSI
                // ==========================================
                // Teks "GRAND TOTAL" diletakkan di bawah "Catatan" (G), angkanya di bawah "Nominal" (H)
                $sheet->setCellValue('G'.$row, 'GRAND TOTAL');
                $sheet->setCellValue('H'.$row, $this->summaryTotal['uang_total'] ?? 0); 
                
                $sheet->getStyle('G'.$row.':H'.$row)->applyFromArray([
                    'font' => ['bold' => true],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFC6E0B4']], 
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
                ]);
                $sheet->getStyle('H'.$row)->getNumberFormat()->setFormatCode('_("Rp"* #,##0_);_("Rp"* \(#,##0\);_("Rp"* "-"??_);_(@_)');

                // ==========================================
                // 6. KOTAK REKAPITULASI GRAND TOTAL BAWAH
                // ==========================================
                // Untuk kotak rincian di bawah tidak ada perubahan posisi, tetap pakai kolom A,B,C,D
                $startRow = $row + 2; 

                $sheet->setCellValue('A'.$startRow, 'GRAND TOTAL');
                $sheet->mergeCells("A{$startRow}:D{$startRow}");
                $sheet->getStyle("A{$startRow}:D{$startRow}")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['argb' => Color::COLOR_WHITE]], 
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF16A34A']] 
                ]);

                $rowsDataKiri = [
                    ['TRANSFER BCA:', $this->summaryTotal['transfer'] ?? 0],
                    ['DEBIT BCA:', $this->summaryTotal['debit'] ?? 0],
                    ['QRIS BCA:', $this->summaryTotal['qris'] ?? 0],
                    ['CASH ON HAND:', $this->summaryTotal['cash'] ?? 0],
                    ['BALANCE', $this->summaryTotal['balance_merah'] ?? 0],
                    ['PENGELUARAN:', $this->summaryTotal['pengeluaran'] ?? 0],
                    ['REAL CASH:', $this->summaryTotal['real_cash'] ?? 0],
                    ['BALANCE', $this->summaryTotal['balance_hijau'] ?? 0],
                ];

                $rowsDataKananAtas = [
                    ['MEMBER:', $this->summaryTotal['uang_member'] ?? 0],
                    ['VISIT:', $this->summaryTotal['uang_visit'] ?? 0],
                    ['PERSONAL TRAINER:', $this->summaryTotal['uang_pt'] ?? 0],
                    ['BALANCE', $this->summaryTotal['uang_total'] ?? 0],
                    ['CATATAN PENGELUARAN', ''] 
                ];

                $pengeluaranList = [];
                if (isset($this->summaryTotal['rincian_pengeluaran']) && count($this->summaryTotal['rincian_pengeluaran']) > 0) {
                    foreach ($this->summaryTotal['rincian_pengeluaran'] as $exp) {
                        $namaAdmin = $exp->admin->name ?? '-';
                        $pengeluaranList[] = [
                            '- ' . $exp->description . ' [Admin: ' . $namaAdmin . ']', 
                            $exp->amount
                        ];
                    }
                } else {
                    $pengeluaranList[] = ['- Tidak ada pengeluaran', null];
                }

                $totalBarisKiri = count($rowsDataKiri);
                $totalBarisKanan = count($rowsDataKananAtas) + count($pengeluaranList);
                $jumlahBarisKotak = max($totalBarisKiri, $totalBarisKanan);
                $endRow = $startRow + $jumlahBarisKotak;

                $sheet->getStyle("A{$startRow}:D{$endRow}")->applyFromArray([
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
                ]);

                $currentRow = $startRow + 1;
                for ($i = 0; $i < $jumlahBarisKotak; $i++) {
                    
                    // --- SISI KIRI (A & B) ---
                    if (isset($rowsDataKiri[$i])) {
                        $sheet->setCellValue('A'.$currentRow, $rowsDataKiri[$i][0]);
                        $sheet->setCellValue('B'.$currentRow, $rowsDataKiri[$i][1]);

                        if (in_array($i, [3, 4, 7])) {
                            $sheet->getStyle("A{$currentRow}:B{$currentRow}")->getFont()->setBold(true);
                        }

                        if ($i === 4) {
                            $sheet->getStyle("A{$currentRow}:B{$currentRow}")->applyFromArray([
                                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFFEE2E2']], 
                                'font' => ['color' => ['argb' => 'FFB91C1C']] 
                            ]);
                        }
                        if ($i === 7) {
                            $sheet->getStyle("A{$currentRow}:B{$currentRow}")->applyFromArray([
                                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFD1FAE5']], 
                                'font' => ['color' => ['argb' => 'FF065F46']] 
                            ]);
                        }
                    }

                    // --- SISI KANAN (C & D) ---
                    if ($i < count($rowsDataKananAtas)) {
                        $sheet->setCellValue('C'.$currentRow, $rowsDataKananAtas[$i][0]);
                        $sheet->setCellValue('D'.$currentRow, $rowsDataKananAtas[$i][1]);

                        if ($i === 3) {
                            $sheet->getStyle("C{$currentRow}:D{$currentRow}")->getFont()->setBold(true);
                            $sheet->getStyle("C{$currentRow}:D{$currentRow}")->applyFromArray([
                                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFD1FAE5']], 
                                'font' => ['color' => ['argb' => 'FF065F46']] 
                            ]);
                        }

                        if ($i === 4) {
                            $sheet->mergeCells("C{$currentRow}:D{$currentRow}");
                            $sheet->getStyle("C{$currentRow}:D{$currentRow}")->applyFromArray([
                                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFF3F4F6']], 
                                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
                            ]);
                        }
                    } else {
                        $indexPengeluaran = $i - count($rowsDataKananAtas);
                        if (isset($pengeluaranList[$indexPengeluaran])) {
                            $sheet->setCellValue('C'.$currentRow, $pengeluaranList[$indexPengeluaran][0]);
                            if ($pengeluaranList[$indexPengeluaran][1] !== null) {
                                $sheet->setCellValue('D'.$currentRow, $pengeluaranList[$indexPengeluaran][1]);
                            } else {
                                $sheet->mergeCells("C{$currentRow}:D{$currentRow}");
                            }
                        }
                    }

                    $currentRow++;
                }

                $sheet->getStyle("B{$startRow}:B{$endRow}")->getNumberFormat()->setFormatCode('_("Rp"* #,##0_);_("Rp"* \(#,##0\);_("Rp"* "-"??_);_(@_)');
                $sheet->getStyle("D{$startRow}:D{$endRow}")->getNumberFormat()->setFormatCode('_("Rp"* #,##0_);_("Rp"* \(#,##0\);_("Rp"* "-"??_);_(@_)');
            }
        ];
    }
}