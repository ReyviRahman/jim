<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Membership;
use App\Models\PtPaymentBatch;
use App\Models\PtSessionCategory;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class SesiPtSlipController extends Controller
{
    public function printPaymentBatch(PtPaymentBatch $batch)
    {
        $batch->load([
            'items.ptBooking.membership.followUp',
            'items.ptBooking.membership.followUpTwo',
            'pt',
        ]);

        $ptSessionCategories = PtSessionCategory::where('pt_id', $batch->pt_id)
            ->latest()
            ->get();

        $rows = [];
        $grandTotalJumlah = 0;
        $grandTotal = 0;

        foreach ($ptSessionCategories as $category) {
            $jumlah = 0;

            foreach ($batch->items as $item) {
                if ($item->ptBooking?->membership?->getPtCategoryLabel() === $category->category) {
                    $jumlah++;
                }
            }

            if ($jumlah > 0) {
                $total = $jumlah * $category->amount;
                $rows[] = [
                    'jenis' => $category->category,
                    'jumlah' => $jumlah,
                    'total' => $total,
                ];

                $grandTotalJumlah += $jumlah;
                $grandTotal += $total;
            }
        }

        $terbilang = $this->terbilang($grandTotal);

        $pdf = Pdf::loadView('pages.dashboard.admin.sesi-pt.payment-batch-pdf', [
            'batch' => $batch,
            'rows' => $rows,
            'grandTotalJumlah' => $grandTotalJumlah,
            'grandTotal' => $grandTotal,
            'terbilang' => $terbilang,
        ]);

        $fileName = 'Pembayaran_PT_'.str_replace(' ', '_', $batch->pt?->name ?? 'Unknown').'_Batch_'.$batch->id.'.pdf';

        return $pdf->download($fileName);
    }

    public function printAttendance(Membership $membership)
    {
        $membership->load([
            'user',
            'members',
            'personalTrainer',
            'ptBookings.pt',
        ]);

        $totalSessions = ($membership->total_sessions ?? 0) + ($membership->sesi_ditambahkan ?? 0);

        $startDate = Carbon::parse($membership->start_date ?? $membership->created_at);
        $endDate = Carbon::parse($membership->pt_end_date);
        $durationMonths = $startDate->diffInMonths($endDate);

        $allMembers = collect([$membership->user])->merge($membership->members)->unique('id')->filter()->values();

        $attendedBookings = $membership->ptBookings
            ->where('attendance', 'attended')
            ->sortBy('booking_date')
            ->values();

        $pdf = Pdf::loadView('pages.dashboard.admin.sesi-pt.attendance-pdf', [
            'membership' => $membership,
            'allMembers' => $allMembers,
            'totalSessions' => $totalSessions,
            'durationMonths' => $durationMonths,
            'attendedBookings' => $attendedBookings,
        ]);

        $primaryName = str_replace(' ', '_', $membership->user?->name ?? 'Unknown');
        $fileName = 'Absensi_PT_'.$membership->id.'_'.$primaryName.'.pdf';

        return $pdf->download($fileName);
    }

    private function terbilang(int $number): string
    {
        $angka = [
            0 => 'nol', 1 => 'satu', 2 => 'dua', 3 => 'tiga', 4 => 'empat',
            5 => 'lima', 6 => 'enam', 7 => 'tujuh', 8 => 'delapan', 9 => 'sembilan',
            10 => 'sepuluh', 11 => 'sebelas', 12 => 'dua belas', 13 => 'tiga belas',
            14 => 'empat belas', 15 => 'lima belas', 16 => 'enam belas',
            17 => 'tujuh belas', 18 => 'delapan belas', 19 => 'sembilan belas',
            20 => 'dua puluh', 30 => 'tiga puluh', 40 => 'empat puluh',
            50 => 'lima puluh', 60 => 'enam puluh', 70 => 'tujuh puluh',
            80 => 'delapan puluh', 90 => 'sembilan puluh',
        ];

        if ($number < 0) {
            return 'minus '.$this->terbilang(-$number);
        }

        if ($number < 21) {
            return $angka[$number];
        }

        if ($number < 100) {
            $puluh = floor($number / 10) * 10;
            $sisa = $number % 10;

            return $angka[$puluh].($sisa > 0 ? ' '.$angka[$sisa] : '');
        }

        if ($number < 1000) {
            $ratus = floor($number / 100);
            $sisa = $number % 100;
            $prefix = $ratus === 1 ? 'seratus' : $angka[$ratus].' ratus';

            return $prefix.($sisa > 0 ? ' '.$this->terbilang($sisa) : '');
        }

        if ($number < 1000000) {
            $ribu = floor($number / 1000);
            $sisa = $number % 1000;
            $prefix = $ribu === 1 ? 'seribu' : $this->terbilang($ribu).' ribu';

            return $prefix.($sisa > 0 ? ' '.$this->terbilang($sisa) : '');
        }

        if ($number < 1000000000) {
            $juta = floor($number / 1000000);
            $sisa = $number % 1000000;

            return $this->terbilang($juta).' juta'.($sisa > 0 ? ' '.$this->terbilang($sisa) : '');
        }

        $miliar = floor($number / 1000000000);
        $sisa = $number % 1000000000;

        return $this->terbilang($miliar).' miliar'.($sisa > 0 ? ' '.$this->terbilang($sisa) : '');
    }
}
