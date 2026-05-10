<?php

namespace App\Exports;

use App\Models\Membership;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PtScheduleExport implements FromQuery, WithHeadings, WithMapping, WithStyles
{
    use Exportable;

    public $search;

    public $filterPt;

    private $rowNumber = 0;

    public function __construct($search, $filterPt)
    {
        $this->search = $search;
        $this->filterPt = $filterPt;
    }

    public function query()
    {
        $query = Membership::with(['user', 'members', 'personalTrainer', 'ptPackage', 'ptSchedule.days'])
            ->leftJoin('pt_schedules', 'memberships.id', '=', 'pt_schedules.membership_id')
            ->whereNotNull('pt_package_id')
            ->where('memberships.status', '!=', 'completed')
            ->select('memberships.*')
            ->orderByRaw("CASE WHEN pt_schedules.status = 'pending' THEN 0 ELSE 1 END")
            ->orderBy('memberships.start_date', 'desc');

        if (! empty($this->search)) {
            $query->where(function ($q) {
                $q->whereHas('user', function ($subQ) {
                    $subQ->where('name', 'like', '%'.$this->search.'%');
                })->orWhereHas('members', function ($subQ) {
                    $subQ->where('name', 'like', '%'.$this->search.'%');
                })->orWhereHas('personalTrainer', function ($subQ) {
                    $subQ->where('name', 'like', '%'.$this->search.'%');
                });
            });
        }

        if (! empty($this->filterPt)) {
            $query->where('memberships.pt_id', $this->filterPt);
        }

        return $query;
    }

    public function headings(): array
    {
        return [
            'No',
            'Tanggal Join',
            'Masa Aktif',
            'Sesi',
            'Kategori',
            'Coach',
            'Nama Member',
            'Tipe Jadwal',
            'Senin',
            'Selasa',
            'Rabu',
            'Kamis',
            'Jumat',
            'Sabtu',
            'Minggu',
            'Status',
        ];
    }

    public function map($membership): array
    {
        $schedule = $membership->ptSchedule;

        $memberNames = [$membership->user?->name];
        foreach ($membership->members as $member) {
            if ($member->id !== $membership->user_id) {
                $memberNames[] = $member->name;
            }
        }
        $allMembers = implode(', ', array_filter($memberNames));

        $category = $membership->ptPackage->category ?? '-';
        $categoryDisplay = $category === 'single' ? 'Personal' : $category;

        $status = '-';
        $dayColumns = array_fill_keys(['senin', 'selasa', 'rabu', 'kamis', 'jumat', 'sabtu', 'minggu'], '-');

        if ($schedule) {
            $status = $schedule->status;

            if ($schedule->type === 'keep') {
                foreach ($schedule->days as $day) {
                    $dayColumns[$day->day] = $day->time->format('H:i');
                }
            }
        }

        return [
            ++$this->rowNumber,
            $membership->start_date?->locale('id')->isoFormat('D MMM YYYY') ?? 'BELUM AKTIF',
            $membership->pt_end_date?->locale('id')->isoFormat('D MMM YYYY') ?? 'BELUM AKTIF',
            $membership->total_sessions ?? 0,
            ucfirst($categoryDisplay),
            $membership->personalTrainer?->name ?? '-',
            $allMembers ?: '-',
            $schedule?->type ?? '-',
            $dayColumns['senin'],
            $dayColumns['selasa'],
            $dayColumns['rabu'],
            $dayColumns['kamis'],
            $dayColumns['jumat'],
            $dayColumns['sabtu'],
            $dayColumns['minggu'],
            ucfirst($status),
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $highestRow = $sheet->getHighestRow();
        $highestColumn = $sheet->getHighestColumn();

        $sheet->getStyle('A1:'.$highestColumn.$highestRow)->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '000000'],
                ],
            ],
        ]);

        $sheet->getStyle('A1:'.$highestColumn.'1')->applyFromArray([
            'font' => [
                'bold' => true,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'D9E1F2'],
            ],
        ]);
    }
}
