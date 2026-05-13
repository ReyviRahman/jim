<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class BeverageStockExport implements WithMultipleSheets
{
    use Exportable;

    private ?string $search;

    private string $startDate;

    private string $endDate;

    private bool $isAdmin;

    public function __construct(?string $search, string $startDate, string $endDate, bool $isAdmin = false)
    {
        $this->search = $search;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->isAdmin = $isAdmin;
    }

    public function sheets(): array
    {
        $sheets = [];
        $current = strtotime($this->startDate);
        $end = strtotime($this->endDate);

        while ($current <= $end) {
            $date = date('Y-m-d', $current);
            $sheets[] = new BeverageStockPerDateSheet(
                $date,
                $this->search,
                $this->isAdmin
            );
            $current = strtotime('+1 day', $current);
        }

        return $sheets;
    }
}
