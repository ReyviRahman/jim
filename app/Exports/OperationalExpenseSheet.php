<?php

namespace App\Exports;

use App\Actions\OperationalExpenseReport;
use Maatwebsite\Excel\Concerns\FromGenerator;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class OperationalExpenseSheet extends DefaultValueBinder implements FromGenerator, ShouldAutoSize, WithColumnFormatting, WithCustomValueBinder, WithHeadings, WithStrictNullComparison, WithStyles, WithTitle
{
    public function __construct(private OperationalExpenseReport $report, private bool $summary) {}

    public function title(): string
    {
        return $this->summary ? 'Ringkasan' : 'Rincian';
    }

    public function headings(): array
    {
        return [...$this->report->headings(), $this->summary
            ? ['Sumber', 'Nilai Operasional', 'Pengeluaran']
            : ['Tanggal', 'Sumber', 'Jenis', 'Referensi', 'Uraian', 'Pencatat / Staf', 'Shift', 'Nominal']];
    }

    public function generator(): \Generator
    {
        if ($this->summary) {
            $summary = $this->report->summary();
            foreach ($summary as $source => $totals) {
                yield [$source, $totals['operasional'], $totals['pengeluaran']];
            }
            yield ['Total Operasional dan Pengeluaran', $summary['Total']['operasional'] + $summary['Total']['pengeluaran']];
        } else {
            foreach ($this->report->details()->cursor() as $row) {
                yield [$row->occurred_at, $row->source, $row->kind, $row->reference ?: '—', $row->description ?: '—', $row->staff ?: '—', $row->shift ?: '—', (int) $row->amount];
            }
        }
    }

    public function bindValue(Cell $cell, mixed $value): bool
    {
        if (is_string($value)) {
            $cell->setValueExplicit($value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }

    public function columnFormats(): array
    {
        $format = '"Rp "#,##0';

        return $this->summary ? ['B' => $format, 'C' => $format] : ['H' => $format];
    }

    public function styles(Worksheet $sheet): array
    {
        if ($this->summary) {
            $sheet->mergeCells('B9:C9');
            $sheet->getStyle('A9:C9')->getFont()->setBold(true);
        }

        return [1 => ['font' => ['bold' => true]], 5 => ['font' => ['bold' => true]]];
    }
}
