<?php

namespace App\Exports;

use App\Models\Membership;
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
        $memberships = Membership::with(['user', 'gymPackage', 'ptPackage', 'followUp', 'followUpTwo'])
            ->where(function ($query) {
                $query->where('follow_up_id', $this->staffUserId)
                      ->orWhere('follow_up_id_two', $this->staffUserId);
            })
            ->whereIn('status', ['active', 'completed'])
            ->when($this->search, function ($query) {
                $query->whereHas('user', function ($q) {
                    $q->where('name', 'like', '%' . $this->search . '%');
                });
            })
            ->when($this->startDate && $this->endDate, function ($query) {
                $query->whereBetween('start_date', [
                    $this->startDate . ' 00:00:00',
                    $this->endDate . ' 23:59:59'
                ]);
            })
            ->latest('start_date')
            ->get();

        // Membuat Format Judul (Contoh: 16 JANUARI - 15 FEBRUARI)
        $titleDate = '';
        if ($this->startDate && $this->endDate) {
            $start = Carbon::parse($this->startDate)->locale('id');
            $end = Carbon::parse($this->endDate)->locale('id');
            
            if ($this->startDate === $this->endDate) {
                $titleDate = strtoupper($start->translatedFormat('d F Y'));
            } else {
                $titleDate = strtoupper($start->translatedFormat('d F')) . ' - ' . strtoupper($end->translatedFormat('d F'));
            }
        }

        return view('exports.rekap-bonus', [
            'memberships' => $memberships,
            'staffUser' => $staffUser,
            'titleDate' => $titleDate
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