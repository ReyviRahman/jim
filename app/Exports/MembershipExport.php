<?php

namespace App\Exports;

use App\Models\Membership;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class MembershipExport implements FromQuery, WithHeadings, WithMapping
{
    use Exportable;

    public $search;
    public $filterTime;
    public $dateStart;
    public $dateEnd;
    
    // 1. Tambahkan variabel untuk menyimpan nomor urut
    private $rowNumber = 0; 

    public function __construct($search, $filterTime, $dateStart, $dateEnd)
    {
        $this->search = $search;
        $this->filterTime = $filterTime;
        $this->dateStart = $dateStart;
        $this->dateEnd = $dateEnd;
    }

    public function query()
    {
        $query = Membership::query()->with(['user', 'members', 'personalTrainer', 'gymPackage', 'ptPackage']);

        if (!empty($this->search)) {
            $query->where(function ($q) {
                $q->whereHas('user', function ($subQ) {
                    $subQ->where('name', 'like', '%' . $this->search . '%');
                })->orWhereHas('members', function ($subQ) {
                    $subQ->where('name', 'like', '%' . $this->search . '%');
                });
            });
        }

        if ($this->filterTime === 'today') {
            $query->whereDate('created_at', today());
        } elseif ($this->filterTime === 'week') {
            $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
        } elseif ($this->filterTime === 'month') {
            $query->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year);
        } elseif ($this->filterTime === 'custom' && $this->dateStart && $this->dateEnd) {
            $query->whereBetween('created_at', [
                $this->dateStart . ' 00:00:00', 
                $this->dateEnd . ' 23:59:59'
            ]);
        }

        return $query->latest();
    }

    public function headings(): array
    {
        return [
            'No', // 2. Ubah heading 'ID' menjadi 'No'
            'Tipe',
            'Nama Member',
            'Paket Gym',
            'Paket PT',
            'Sesi Tersisa',
            'Total Sesi',
            'Total Bayar',
            'Status',
            'Tanggal Daftar'
        ];
    }

    public function map($membership): array
    {
        $memberNames = $membership->members->pluck('name')->join(', ');
        if (empty($memberNames)) {
            $memberNames = $membership->user->name ?? 'N/A';
        }

        return [
            ++$this->rowNumber, // 3. Gunakan increment untuk nomor urut
            $membership->type,
            $memberNames,
            $membership->gymPackage->name ?? '-',
            $membership->ptPackage->name ?? '-',
            $membership->remaining_sessions ?? 0,
            $membership->total_sessions ?? 0,
            $membership->price_paid,
            $membership->status,
            $membership->created_at->format('Y-m-d H:i:s'),
        ];
    }
}