<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BonusPayment;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class BonusPaymentPdfController extends Controller
{
    public function __invoke(User $user, int $paymentId): Response
    {
        abort_unless(in_array(auth()->user()?->role, ['admin', 'head_coach'], true), 403);

        $payment = BonusPayment::query()
            ->whereBelongsTo($user, 'staffUser')
            ->with([
                'staffUser:id,name',
                'paidBy:id,name',
                'items' => fn ($query) => $query
                    ->select([
                        'id',
                        'bonus_payment_id',
                        'member_name',
                        'package_name',
                        'nominal',
                        'nominal_akhir',
                        'payment_date',
                    ])
                    ->orderBy('id'),
            ])
            ->find($paymentId);

        abort_unless($payment, 404);

        $staffSlug = Str::slug($payment->staffUser?->name ?? '', '_') ?: 'staff';
        $fileName = 'Pembayaran_Bonus_'.$staffSlug.'_Batch_'.$payment->id.'.pdf';

        $response = Pdf::loadView('pages.dashboard.admin.rekap-bonus.payment-pdf', [
            'payment' => $payment,
            'terbilang' => $this->terbilang((int) round((float) $payment->net_amount)),
        ])->setPaper('a4')->download($fileName);

        $response->headers->set('Cache-Control', 'private, no-store, max-age=0');

        return $response;
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
            $puluh = (int) floor($number / 10) * 10;
            $sisa = $number % 10;

            return $angka[$puluh].($sisa > 0 ? ' '.$angka[$sisa] : '');
        }

        if ($number < 1000) {
            $ratus = (int) floor($number / 100);
            $sisa = $number % 100;
            $prefix = $ratus === 1 ? 'seratus' : $angka[$ratus].' ratus';

            return $prefix.($sisa > 0 ? ' '.$this->terbilang($sisa) : '');
        }

        if ($number < 1000000) {
            $ribu = (int) floor($number / 1000);
            $sisa = $number % 1000;
            $prefix = $ribu === 1 ? 'seribu' : $this->terbilang($ribu).' ribu';

            return $prefix.($sisa > 0 ? ' '.$this->terbilang($sisa) : '');
        }

        if ($number < 1000000000) {
            $juta = (int) floor($number / 1000000);
            $sisa = $number % 1000000;

            return $this->terbilang($juta).' juta'.($sisa > 0 ? ' '.$this->terbilang($sisa) : '');
        }

        $miliar = (int) floor($number / 1000000000);
        $sisa = $number % 1000000000;

        return $this->terbilang($miliar).' miliar'.($sisa > 0 ? ' '.$this->terbilang($sisa) : '');
    }
}
