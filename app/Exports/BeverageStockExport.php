<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;

class BeverageStockExport implements WithEvents, WithTitle
{
    use Exportable;

    private BeverageStockCombinedSheet $sheet;

    public function __construct(?string $search, string $startDate, string $endDate, bool $isAdmin = false)
    {
        $this->sheet = new BeverageStockCombinedSheet($startDate, $endDate, $search, $isAdmin);
    }

    public function title(): string
    {
        return $this->sheet->title();
    }

    public function registerEvents(): array
    {
        return $this->sheet->registerEvents();
    }
}
