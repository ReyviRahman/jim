<?php

namespace App\Exports;

use App\Models\BeverageSale;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

class BeverageSaleExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithStyles, WithEvents
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

        if (!empty($this->searchProduct)) {
            $query->where(function ($q) {
                $q->whereHas('beverage', function ($q2) {
                    $q2->where('nama_produk', 'like', '%' . $this->searchProduct . '%');
                })
                ->orWhere('nama_staff', 'like', '%' . $this->searchProduct . '%');
            });
        }

        if (!empty($this->start_date)) {
            $query->whereDate('waktu_transaksi', '>=', $this->start_date);
        }

        if (!empty($this->end_date)) {
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
            'qris' => 'QRIS',
            'tf_bca' => 'Transfer BCA',
            'lunas' => 'Lunas',
            'deposit_hutang' => 'Deposit/Hutang',
            'belum_bayar' => 'Belum Bayar',
        ];

        return [
            ++$this->rowNumber,
            $sale->waktu_transaksi->format('d M Y H:i'),
            $sale->nama_staff,
            $sale->beverage->nama_produk ?? '-',
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
                $lastRow = $sheet->getHighestRow() + 1;

                $sheet->setCellValue('A' . $lastRow, 'GRAND TOTAL');
                $sheet->setCellValue('E' . $lastRow, $this->totalItem);
                $sheet->setCellValue('G' . $lastRow, $this->grandTotal);

                $sheet->getStyle('A' . $lastRow . ':I' . $lastRow)->applyFromArray([
                    'font' => ['bold' => true],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF16A34A']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => Color::COLOR_BLACK]]]
                ]);

                $sheet->getStyle('E' . $lastRow)->applyFromArray([
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
                ]);

                $sheet->getStyle('G' . $lastRow)->getNumberFormat()->setFormatCode('_("Rp"* #,##0_);_("Rp"* \(#,##0\);_("Rp"* "-"??_);_(@_)');
            }
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}