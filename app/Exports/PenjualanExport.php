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
    protected $tanggal;
    protected $shift; // Tambahkan variabel shift

    public function __construct($transactions, $summaryTotal, $tanggal, $shift)
    {
        $this->transactions = $transactions;
        $this->summaryTotal = $summaryTotal;
        $this->tanggal = $tanggal;
        $this->shift = $shift;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                
                $sheet->setTitle(Carbon::parse($this->tanggal)->translatedFormat('d F Y'));

                // ==========================================
                // 1. PENGATURAN LEBAR KOLOM (A - J)
                // ==========================================
                $columns = ['A'=>20, 'B'=>15, 'C'=>20, 'D'=>20, 'E'=>30, 'F'=>18, 'G'=>15, 'H'=>15, 'I'=>12, 'J'=>12];
                foreach ($columns as $col => $width) {
                    $sheet->getColumnDimension($col)->setWidth($width);
                }

                // ==========================================
                // 2. JUDUL PALING ATAS (Baris 1)
                // ==========================================
                $shiftText = strtoupper($this->shift === 'all' ? 'PAGI & SIANG' : $this->shift);
                $judulBesar = "PENJUALAN ADMIN " . $shiftText;
                
                $sheet->setCellValue('A1', $judulBesar);
                $sheet->mergeCells('A1:J1');
                $sheet->getStyle('A1:J1')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 14], // Ukuran font diperbesar
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                ]);
                $sheet->getRowDimension(1)->setRowHeight(25);

                // ==========================================
                // 3. HEADER TABEL UTAMA (Baris 3) -> Digeser ke baris 3
                // ==========================================
                $headers = ['NAMA', 'TANGGAL BAYAR', 'TANGGAL MULAI AKTIF', 'TANGGAL BERAKHIR (MASA AKTIF)', 'STATUS', 'PAKET MEMBER', 'NOMINAL', 'METODE BAYAR', 'ADMIN'];
                foreach (array_values($headers) as $index => $header) {
                    $col = chr(65 + $index);
                    $sheet->setCellValue($col . '3', $header);
                }

                $sheet->getStyle('A3:I3')->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['argb' => Color::COLOR_WHITE]],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1F2937']], 
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => Color::COLOR_BLACK]]]
                ]);
                $sheet->getRowDimension(3)->setRowHeight(30);

                // ==========================================
                // 4. ISI DATA TRANSAKSI (Mulai Baris 4)
                // ==========================================
                $row = 4; // Data dimulai dari baris 4
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
                    $sheet->setCellValue('G'.$row, $trx->amount);
                    $sheet->setCellValue('H'.$row, strtoupper($trx->payment_method));
                    $sheet->setCellValue('I'.$row, strtoupper($trx->admin->name ?? '-'));

                    $row++;
                }

                $lastDataRow = $row - 1;
                if ($lastDataRow >= 4) { // Pengecekan disesuaikan ke baris 4
                    $sheet->getStyle('A4:I'.$lastDataRow)->applyFromArray([
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFCCFFFF']], 
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
                    ]);
                    $sheet->getStyle('G4:G'.$lastDataRow)->getNumberFormat()->setFormatCode('_("Rp"* #,##0_);_("Rp"* \(#,##0\);_("Rp"* "-"??_);_(@_)');
                }

                // ==========================================
                // 5. BARIS TOTAL TRANSAKSI
                // ==========================================
                $sheet->setCellValue('F'.$row, 'GRAND TOTAL');
                
                // Hapus rumus =SUM() dan ganti dengan variabel total yang sudah dihitung di PHP
                $sheet->setCellValue('G'.$row, $this->summaryTotal['uang_total'] ?? 0); 
                
                $sheet->getStyle('F'.$row.':G'.$row)->applyFromArray([
                    'font' => ['bold' => true],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFC6E0B4']], 
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
                ]);
                $sheet->getStyle('G'.$row)->getNumberFormat()->setFormatCode('_("Rp"* #,##0_);_("Rp"* \(#,##0\);_("Rp"* "-"??_);_(@_)');

                // ==========================================
                // 6. KOTAK REKAPITULASI GRAND TOTAL BAWAH
                // ==========================================
                $startRow = $row + 2; 

                // 6.1. Header Kotak Total (Merge full A-D, BG Hijau Tua)
                $sheet->setCellValue('A'.$startRow, 'GRAND TOTAL');
                $sheet->mergeCells("A{$startRow}:D{$startRow}");
                $sheet->getStyle("A{$startRow}:D{$startRow}")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['argb' => Color::COLOR_WHITE]], 
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF16A34A']] 
                ]);

                // 6.2. Siapkan data statis untuk sisi kiri dan atas sisi kanan
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
                    ['CATATAN PENGELUARAN', ''] // Judul Catatan
                ];

                // 6.3. Siapkan rincian pengeluaran untuk di-loop nanti
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

                // Hitung total baris yang dibutuhkan (ambil yang terpanjang antara kiri atau kanan)
                $totalBarisKiri = count($rowsDataKiri);
                $totalBarisKanan = count($rowsDataKananAtas) + count($pengeluaranList);
                $jumlahBarisKotak = max($totalBarisKiri, $totalBarisKanan);
                $endRow = $startRow + $jumlahBarisKotak;

                // Set Border untuk seluruh kotak rekapitulasi agar rapi sampai bawah
                $sheet->getStyle("A{$startRow}:D{$endRow}")->applyFromArray([
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
                ]);

                // 6.4. Loop untuk mengisi tabel (Kiri dan Kanan bersamaan tiap baris)
                $currentRow = $startRow + 1;
                for ($i = 0; $i < $jumlahBarisKotak; $i++) {
                    
                    // --- SISI KIRI (A & B) ---
                    if (isset($rowsDataKiri[$i])) {
                        $sheet->setCellValue('A'.$currentRow, $rowsDataKiri[$i][0]);
                        $sheet->setCellValue('B'.$currentRow, $rowsDataKiri[$i][1]);

                        // Bold baris Balance Kiri (Index 3, 4, 7)
                        if (in_array($i, [3, 4, 7])) {
                            $sheet->getStyle("A{$currentRow}:B{$currentRow}")->getFont()->setBold(true);
                        }

                        // BG Merah (Index 4)
                        if ($i === 4) {
                            $sheet->getStyle("A{$currentRow}:B{$currentRow}")->applyFromArray([
                                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFFEE2E2']], 
                                'font' => ['color' => ['argb' => 'FFB91C1C']] 
                            ]);
                        }
                        // BG Hijau (Index 7)
                        if ($i === 7) {
                            $sheet->getStyle("A{$currentRow}:B{$currentRow}")->applyFromArray([
                                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFD1FAE5']], 
                                'font' => ['color' => ['argb' => 'FF065F46']] 
                            ]);
                        }
                    }

                    // --- SISI KANAN (C & D) ---
                    if ($i < count($rowsDataKananAtas)) {
                        // Jika masih baris kategori atas (Member, Visit, dll)
                        $sheet->setCellValue('C'.$currentRow, $rowsDataKananAtas[$i][0]);
                        $sheet->setCellValue('D'.$currentRow, $rowsDataKananAtas[$i][1]);

                        // Bold Balance Kanan (Index 3)
                        if ($i === 3) {
                            $sheet->getStyle("C{$currentRow}:D{$currentRow}")->getFont()->setBold(true);
                            $sheet->getStyle("C{$currentRow}:D{$currentRow}")->applyFromArray([
                                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFD1FAE5']], 
                                'font' => ['color' => ['argb' => 'FF065F46']] 
                            ]);
                        }

                        // Judul Catatan (Index 4)
                        if ($i === 4) {
                            $sheet->mergeCells("C{$currentRow}:D{$currentRow}");
                            $sheet->getStyle("C{$currentRow}:D{$currentRow}")->applyFromArray([
                                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFF3F4F6']], 
                                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
                            ]);
                        }
                    } else {
                        // Jika baris kategori atas sudah habis, mulai print isi pengeluaran
                        $indexPengeluaran = $i - count($rowsDataKananAtas);
                        if (isset($pengeluaranList[$indexPengeluaran])) {
                            $sheet->setCellValue('C'.$currentRow, $pengeluaranList[$indexPengeluaran][0]);
                            if ($pengeluaranList[$indexPengeluaran][1] !== null) {
                                $sheet->setCellValue('D'.$currentRow, $pengeluaranList[$indexPengeluaran][1]);
                            } else {
                                // Jika "Tidak ada pengeluaran", merge C dan D
                                $sheet->mergeCells("C{$currentRow}:D{$currentRow}");
                            }
                        }
                    }

                    $currentRow++;
                }

                // 6.5. Terapkan format Rupiah ke semua baris angka (Kecuali bagian yang kosong)
                $sheet->getStyle("B{$startRow}:B{$endRow}")->getNumberFormat()->setFormatCode('_("Rp"* #,##0_);_("Rp"* \(#,##0\);_("Rp"* "-"??_);_(@_)');
                $sheet->getStyle("D{$startRow}:D{$endRow}")->getNumberFormat()->setFormatCode('_("Rp"* #,##0_);_("Rp"* \(#,##0\);_("Rp"* "-"??_);_(@_)');
            }
        ];
    }
}