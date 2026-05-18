<?php

namespace App\Exports;

use App\Models\Membership;
use App\Models\SalesKonsultan;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class RekapBonusExport implements FromView, ShouldAutoSize, WithStyles
{
    public $staffUserId;

    public $search;

    public $startDate;

    public $endDate;

    public function __construct($staffUserId, $search, $startDate, $endDate)
    {
        $this->staffUserId = $staffUserId;
        $this->search = $search;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }

    public function view(): View
    {
        $staffUser = User::findOrFail($this->staffUserId);

        // Tambahkan 'followUp', 'followUpTwo' di dalam array with()
        $memberships = Membership::with(['user', 'gymPackage', 'ptPackage', 'followUp', 'followUpTwo', 'transactions'])
            ->where(function ($query) {
                $query->where('follow_up_id', $this->staffUserId)
                    ->orWhere('follow_up_id_two', $this->staffUserId);
            })
            ->where('type', '!=', 'visit')
            ->where('payment_status', 'paid')
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->whereHas('members', function ($sub) {
                        $sub->where('name', 'like', '%'.$this->search.'%');
                    })
                        ->orWhereHas('user', function ($sub) {
                            $sub->where('name', 'like', '%'.$this->search.'%');
                        });
                });
            })
            ->when($this->startDate && $this->endDate, function ($query) {
                $query->whereHas('transactions', function ($q) {
                    $q->whereBetween('payment_date', [
                        $this->startDate.' 00:00:00',
                        $this->endDate.' 23:59:59',
                    ]);
                });
            })
            ->withMax('transactions', 'payment_date')
            ->orderByDesc('transactions_max_payment_date')
            ->get();

        $totalNominalAkhir = 0;
        foreach ($memberships as $membership) {
            $totalNominalAkhir += $membership->calculateNominalAkhir();
        }

        $range = SalesKonsultan::findByNominal($totalNominalAkhir);

        $bonusInfo = [
            'rentang_satu' => null,
            'rentang_dua' => null,
            'persen' => 0,
            'total_bonus' => 0,
        ];

        if ($range) {
            $persen = (float) $range->persen;
            $bonusInfo = [
                'rentang_satu' => $range->rentang_satu,
                'rentang_dua' => $range->rentang_dua,
                'persen' => $persen,
                'total_bonus' => $totalNominalAkhir * ($persen / 100),
            ];
        }

        // Membuat Format Judul (Contoh: 16 JANUARI - 15 FEBRUARI)
        $titleDate = '';
        if ($this->startDate && $this->endDate) {
            $start = Carbon::parse($this->startDate)->locale('id');
            $end = Carbon::parse($this->endDate)->locale('id');

            if ($this->startDate === $this->endDate) {
                $titleDate = strtoupper($start->translatedFormat('d F Y'));
            } else {
                $titleDate = strtoupper($start->translatedFormat('d F')).' - '.strtoupper($end->translatedFormat('d F'));
            }
        }

        return view('exports.rekap-bonus', [
            'memberships' => $memberships,
            'staffUser' => $staffUser,
            'titleDate' => $titleDate,
            'totalNominalAkhir' => $totalNominalAkhir,
            'bonusInfo' => $bonusInfo,
        ]);
    }

    // Memberikan style tambahan ke Excel secara terprogram
    public function styles(Worksheet $sheet)
    {
        return [
            // Baris 1-4 (Header) dibuat Bold
            1 => ['font' => ['bold' => true]],
            2 => ['font' => ['bold' => true]],
            3 => ['font' => ['bold' => true]],
            4 => ['font' => ['bold' => true]],
        ];
    }
}
