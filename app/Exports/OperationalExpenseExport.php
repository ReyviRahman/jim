<?php

namespace App\Exports;

use App\Actions\OperationalExpenseReport;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class OperationalExpenseExport implements WithMultipleSheets
{
    public function __construct(private OperationalExpenseReport $report) {}

    public function sheets(): array
    {
        return [new OperationalExpenseSheet($this->report, true), new OperationalExpenseSheet($this->report, false)];
    }
}
