<?php

namespace App\Exports;

use App\Models\Beverage;
use App\Models\BeverageInvoice;
use App\Models\BeverageRestock;
use App\Models\BeverageSale;
use App\Models\BeverageStokSnapshot;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
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

        if ($this->startDate) {
            $query->whereDate('tanggal_order', '>=', $this->startDate);
        }
        if ($this->endDate) {
            $query->whereDate('tanggal_order', '<=', $this->endDate);
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
            $monthName = $monthNames[$month].' '.$year;

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
            'stockSummary' => $this->stockSummary($invoices),
        ]);
    }

    /**
     * @param  Collection<int, BeverageInvoice>  $invoices
     * @return array{start: ?string, end: ?string, unmapped: int, rows: array, notes: array}
     */
    private function stockSummary(Collection $invoices): array
    {
        $start = $this->startDate ?: $invoices->min('tanggal_order')?->toDateString();
        $end = $this->endDate ?: $invoices->max('tanggal_order')?->toDateString();
        $items = $invoices->flatMap->items;
        $summary = ['start' => $start, 'end' => $end, 'unmapped' => $items->whereNull('beverage_id')->count(), 'rows' => [], 'notes' => []];
        $ids = $items->pluck('beverage_id')->filter()->unique()->values();
        $invoicePcs = $items->whereNotNull('beverage_id')->groupBy('beverage_id')->map(function ($productItems) {
            $filled = $productItems->whereNotNull('total_pcs');

            return $filled->isEmpty() ? null : (int) $filled->sum('total_pcs');
        });
        if (! $start || ! $end || $ids->isEmpty()) {
            return $summary;
        }

        $products = Beverage::withTrashed()->whereKey($ids)->orderBy('nama_produk')->orderBy('id')->get();
        $snapshots = BeverageStokSnapshot::query()->whereIn('beverage_id', $ids)
            ->where(function ($query) use ($start, $end) {
                $query->where(fn ($query) => $query->where('tanggal', $start)->where('tipe', 'init'))
                    ->orWhere(fn ($query) => $query->where('tanggal', $end)->where('tipe', 'last'));
            })->get()->groupBy('beverage_id');
        $incoming = BeverageRestock::query()->whereIn('beverage_id', $ids)->where('tipe', 'restock')
            ->whereBetween('tanggal', [$start, $end])->select('beverage_id')
            ->selectRaw('SUM(jumlah_tambah) as quantity')->groupBy('beverage_id')->pluck('quantity', 'beverage_id');
        $sales = BeverageSale::query()->whereIn('beverage_id', $ids)
            ->where('waktu_transaksi', '>=', CarbonImmutable::parse($start, 'Asia/Jakarta')->startOfDay())
            ->where('waktu_transaksi', '<', CarbonImmutable::parse($end, 'Asia/Jakarta')->addDay()->startOfDay())
            ->whereNull('parent_beverage_sale_id')
            ->whereNotIn('keterangan_bayar', ['pengeluaran_umum', 'deposit_hutang_cash', 'deposit_hutang_qris'])
            ->select('beverage_id')
            ->selectRaw('SUM(CASE WHEN keterangan_bayar = ? THEN jumlah_beli ELSE 0 END) as operational', ['operasional'])
            ->selectRaw('SUM(CASE WHEN keterangan_bayar <> ? THEN jumlah_beli ELSE 0 END) as sold', ['operasional'])
            ->groupBy('beverage_id')->get()->keyBy('beverage_id');
        $endsToday = $end === now('Asia/Jakarta')->toDateString();

        foreach ($products as $product) {
            $productSnapshots = $snapshots->get($product->id, collect());
            $opening = $productSnapshots->firstWhere('tipe', 'init')?->jumlah;
            $closing = $endsToday ? $product->stok_sekarang : $productSnapshots->firstWhere('tipe', 'last')?->jumlah;
            $added = (int) $incoming->get($product->id, 0);
            $sold = (int) ($sales->get($product->id)?->sold ?? 0);
            $operational = (int) ($sales->get($product->id)?->operational ?? 0);
            $available = $opening === null ? null : $opening + $added;
            $name = $product->nama_produk.($product->trashed() ? ' (Nonaktif)' : '');
            $summary['rows'][] = ['product_id' => $product->id, 'name' => $name, 'opening' => $opening, 'added' => $added, 'available' => $available, 'sold' => $sold, 'closing' => $closing, 'operational' => $operational, 'invoice_pcs' => $invoicePcs->get($product->id)];
            if ($opening === null) {
                $summary['notes'][] = $name.': snapshot init '.$start.' tidak tersedia; Stok Awal dan Jumlah Stok tidak dapat ditampilkan.';
            }
            if ($closing === null) {
                $summary['notes'][] = $name.': snapshot last '.$end.' tidak tersedia; Stok Akhir tidak dapat ditampilkan.';
            }
            if ($available !== null && $closing !== null) {
                $difference = $closing - ($available - $sold - $operational);
                if ($difference !== 0) {
                    $summary['notes'][] = $name.': selisih saldo akhir tercatat terhadap perhitungan '.($difference > 0 ? '+' : '').$difference.' pcs. Data stok tidak diubah.';
                }
            }
        }

        return $summary;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastRow = $sheet->getHighestRow();

                if ($lastRow < 1) {
                    return;
                }

                $summaryRow = null;
                for ($row = 1; $row <= $lastRow; $row++) {
                    if ($sheet->getCell('A'.$row)->getValue() === 'Ringkasan Stok Produk') {
                        $summaryRow = $row;
                        break;
                    }
                }
                $invoiceLastRow = $summaryRow ? $summaryRow - 1 : $lastRow;

                // Apply thin borders to all cells
                $sheet->getStyle('A1:M'.$invoiceLastRow)->applyFromArray([
                    'borders' => [
                        'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFD1D5DB']],
                    ],
                ]);

                // Style header row
                $sheet->getStyle('A1:M1')->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF22C55E']],
                    'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                ]);

                // Currency columns number format
                $currencyColumns = ['H', 'I', 'J', 'K'];
                foreach ($currencyColumns as $col) {
                    $sheet->getStyle($col.'2:'.$col.$invoiceLastRow)
                        ->getNumberFormat()
                        ->setFormatCode('"Rp" #,##0');
                }

                // Alignment adjustments
                $sheet->getStyle('F2:G'.$invoiceLastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('L2:M'.$invoiceLastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                foreach ($currencyColumns as $col) {
                    $sheet->getStyle($col.'2:'.$col.$invoiceLastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                }
                if ($summaryRow) {
                    $headerRow = $summaryRow + 3;
                    $sheet->getStyle('A'.$summaryRow.':H'.$lastRow)->getAlignment()->setWrapText(true);
                    $sheet->getStyle('A'.$summaryRow.':H'.$summaryRow)->getFont()->setBold(true);
                    $sheet->getStyle('A'.$headerRow.':H'.$lastRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                    $sheet->getStyle('A'.$headerRow.':H'.$headerRow)->applyFromArray([
                        'font' => ['bold' => true],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFDBEAFE']],
                    ]);
                    $sheet->getStyle('B'.($headerRow + 1).':H'.$lastRow)->getNumberFormat()->setFormatCode('#,##0');
                }
            },
        ];
    }
}
