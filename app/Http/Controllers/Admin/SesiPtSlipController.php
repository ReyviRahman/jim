<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Membership;
use App\Models\PtSessionCategory;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class SesiPtSlipController extends Controller
{
    public function print(User $user, Request $request)
    {
        $dateStart = $request->input('date_start');
        $dateEnd = $request->input('date_end');

        $memberships = Membership::where('pt_id', $user->id)
            ->when($dateStart && $dateEnd, function ($query) use ($dateStart, $dateEnd) {
                $query->whereDate('start_date', '<=', $dateEnd)
                    ->whereDate('pt_end_date', '>=', $dateStart);
            })
            ->with(['followUp', 'followUpTwo'])
            ->withCount([
                'ptBookings as berjalan' => function ($q) use ($dateStart, $dateEnd) {
                    $q->where('attendance', 'attended');
                    if ($dateStart && $dateEnd) {
                        $q->whereBetween('booking_date', [
                            $dateStart.' 00:00:00',
                            $dateEnd.' 23:59:59',
                        ]);
                    }
                },
            ])
            ->orderBy('created_at', 'desc')
            ->get();

        $ptSessionCategories = PtSessionCategory::where('pt_id', $user->id)
            ->latest()
            ->get();

        $rows = [];
        $grandTotalJumlah = 0;
        $grandTotal = 0;

        foreach ($ptSessionCategories as $category) {
            $jumlah = 0;
            $total = 0;

            foreach ($memberships as $membership) {
                if ($this->getCategoryLabel($membership) === $category->category) {
                    $jumlah += $membership->berjalan;
                    $total += $membership->berjalan * $category->amount;
                }
            }

            $rows[] = [
                'jenis' => $category->category,
                'jumlah' => $jumlah,
                'total' => $total,
            ];

            $grandTotalJumlah += $jumlah;
            $grandTotal += $total;
        }

        $terbilang = $this->terbilang($grandTotal);

        $pdf = Pdf::loadView('pages.dashboard.admin.sesi-pt.slip-pdf', [
            'user' => $user,
            'dateStart' => $dateStart,
            'dateEnd' => $dateEnd,
            'rows' => $rows,
            'grandTotalJumlah' => $grandTotalJumlah,
            'grandTotal' => $grandTotal,
            'terbilang' => $terbilang,
        ]);

        $fileName = 'Slip_PT_'.str_replace(' ', '_', $user->name).'_'.($dateStart ?? 'all').'.pdf';

        return $pdf->download($fileName);
    }

    private function getCategoryLabel(Membership $membership): string
    {
        $followUpRole = $membership->followUp?->role;
        $followUpTwoRole = $membership->followUpTwo?->role;

        if (($followUpRole !== null && $followUpRole !== 'pt') || ($followUpTwoRole !== null && $followUpTwoRole !== 'pt')) {
            return 'SLS';
        }

        $pricePaid = $membership->price_paid;
        $netPrice = $membership->net_price;
        $unrecommendedPrice = $membership->unrecommended_price;

        if ($netPrice !== null) {
            if ($pricePaid > $netPrice) {
                return 'SDR';
            }

            if ($unrecommendedPrice !== null) {
                return $pricePaid > $unrecommendedPrice ? 'IR' : 'SPR';
            }

            return 'IR';
        }

        if ($unrecommendedPrice !== null) {
            return $pricePaid > $unrecommendedPrice ? 'SDR' : 'SPR';
        }

        return 'SDR';
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
