<?php

namespace App\Exports;

use App\Models\BeverageInvoice;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class BeverageInvoiceExport implements FromView, ShouldAutoSize, WithEvents
{
    use Exportable;

    public $startDate;
    public $endDate;

    public function __construct($startDate = null, $endDate = null)
    {
        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }

    public function view(): View
    {
        $query = BeverageInvoice::with('items');

        if ($this->startDate && $this->endDate) {
            $query->whereDate('tanggal_order', '>=', $this->startDate)
                  ->whereDate('tanggal_order', '<=', $this->endDate);
        } elseif ($this->startDate) {
            $query->whereDate('tanggal_order', $this->startDate);
        }

        $invoices = $query->oldest()->get();

        $grouped = $invoices->groupBy(function ($invoice) {
            return $invoice->tanggal_order->format('Y-m');
        });

        $monthNames = [
            '01' => 'Januari', '02' => 'Februari', '03' => 'Maret',
            '04' => 'April', '05' => 'Mei', '06' => 'Juni',
            '07' => 'Juli', '08' => 'Agustus', '09' => 'September',
            '10' => 'Oktober', '11' => 'November', '12' => 'Desember',
        ];

        $monthlyData = [];
        $totalSemua = 0;

        foreach ($grouped as $key => $monthInvoices) {
            $monthTotal = $monthInvoices->sum(function ($inv) {
                return $inv->items->sum('total');
            });
            $totalSemua += $monthTotal;

            [$year, $month] = explode('-', $key);
            $monthName = $monthNames[$month] . ' ' . $year;

            $monthlyData[] = [
                'key' => $key,
                'name' => $monthName,
                'invoices' => $monthInvoices,
                'total' => $monthTotal,
            ];
        }

        return view('exports.beverage-invoice', [
            'monthlyData' => $monthlyData,
            'totalSemua' => $totalSemua,
        ]);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastRow = $sheet->getHighestRow();

                if ($lastRow < 1) return;

                // Apply thin borders to all cells
                $sheet->getStyle('A1:L' . $lastRow)->applyFromArray([
                    'borders' => [
                        'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFD1D5DB']],
                    ],
                ]);

                // Style header row
                $sheet->getStyle('A1:L1')->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF22C55E']],
                    'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                ]);

                // Currency columns number format
                $currencyColumns = ['G', 'H', 'I', 'J'];
                foreach ($currencyColumns as $col) {
                    $sheet->getStyle($col . '2:' . $col . $lastRow)
                        ->getNumberFormat()
                        ->setFormatCode('"Rp" #,##0');
                }

                // Alignment adjustments
                $sheet->getStyle('F2:F' . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('K2:K' . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('L2:L' . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                foreach ($currencyColumns as $col) {
                    $sheet->getStyle($col . '2:' . $col . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                }
            },
        ];
    }
}
