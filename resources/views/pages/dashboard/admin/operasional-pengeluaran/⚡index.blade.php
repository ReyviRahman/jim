<?php

use App\Actions\OperationalExpenseReport;
use App\Exports\OperationalExpenseExport;
use App\Models\BeverageSale;
use App\Models\Expense;
use App\Models\MembershipTransaction;
use App\Models\Shift;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

new #[Layout('layouts::admin')] class extends Component
{
    use WithPagination;

    public string $dateStart = '';

    public string $dateEnd = '';

    public string $source = '';

    public string $kind = '';

    public string $shift = '';

    public function boot(): void
    {
        abort_unless(in_array(auth()->user()?->role, ['admin', 'kasir_gym'], true), 403);
    }

    public function mount(): void
    {
        $this->dateStart = today()->startOfMonth()->toDateString();
        $this->dateEnd = today()->endOfMonth()->toDateString();
    }

    #[Computed]
    public function shiftOptions(): Collection
    {
        return Shift::filterOptions([MembershipTransaction::class, Expense::class, BeverageSale::class]);
    }

    protected function rules(): array
    {
        return [
            'dateStart' => ['required', 'date_format:Y-m-d'],
            'dateEnd' => ['required', 'date_format:Y-m-d', 'after_or_equal:dateStart'],
            'source' => [Rule::in(['', 'Gym', 'Minuman'])],
            'kind' => [Rule::in(['', 'Operasional', 'Pengeluaran'])],
            'shift' => [Rule::in(['', ...$this->shiftOptions->keys()->all()])],
        ];
    }

    public function updated(string $property): void
    {
        if (! in_array($property, ['dateStart', 'dateEnd', 'source', 'kind', 'shift'], true)) {
            return;
        }

        $this->resetPage();
        $this->resetValidation();
        $this->validate();
    }

    public function setDateRange(string $dateStart, string $dateEnd): void
    {
        $this->dateStart = $dateStart;
        $this->dateEnd = $dateEnd;
        $this->resetPage();
        $this->resetValidation();
        $this->validate();
    }

    #[Computed]
    public function report(): ?OperationalExpenseReport
    {
        if (Validator::make($this->only(['dateStart', 'dateEnd', 'source', 'kind', 'shift']), $this->rules())->fails()) {
            return null;
        }

        return new OperationalExpenseReport($this->dateStart, $this->dateEnd, $this->source, $this->kind, $this->shift);
    }

    public function exportExcel(): BinaryFileResponse
    {
        abort_unless(in_array(auth()->user()?->role, ['admin', 'kasir_gym'], true), 403);
        $this->validate();

        return Excel::download(new OperationalExpenseExport($this->report), 'operasional_pengeluaran_'.now()->format('Y-m-d_His').'.xlsx');
    }

    #[Computed]
    public function summary(): array
    {
        return $this->report?->summary() ?? [];
    }

    #[Computed]
    public function rows(): ?LengthAwarePaginator
    {
        return $this->report?->details()->paginate(25);
    }
};
?>

<div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-heading">Operasional &amp; Pengeluaran</h1>
            <p class="mt-1 text-sm text-body">Laporan gabungan Gym dan Minuman. Nilai operasional terpisah dari pengeluaran uang.</p>
        </div>
        <button type="button" wire:click="exportExcel" wire:loading.attr="disabled" @disabled(! $this->report) class="rounded-md bg-green-700 px-4 py-2 text-sm font-medium text-white disabled:opacity-50">
            Unduh Excel
        </button>
    </div>

    <div class="rounded-md border border-default bg-neutral-primary-soft p-4">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <label wire:ignore class="text-sm text-heading">Tanggal
                <input type="text" placeholder="Pilih Rentang Tanggal"
                    x-data="{ picker: null, destroy() { this.picker?.destroy() } }"
                    x-init="picker = flatpickr($el, {
                        mode: 'range',
                        dateFormat: 'Y-m-d',
                        defaultDate: [$wire.dateStart, $wire.dateEnd],
                        onClose: (dates, dateStr, instance) => {
                            const start = dates[0] ? instance.formatDate(dates[0], 'Y-m-d') : '';
                            const end = dates[1] ? instance.formatDate(dates[1], 'Y-m-d') : start;
                            $wire.setDateRange(start, end);
                        }
                    })"
                    class="mt-1 block w-full rounded-md border border-default bg-neutral-primary-soft p-2">
            </label>
            <label class="text-sm text-heading">Sumber
                <select wire:model.live="source" class="mt-1 block w-full rounded-md border border-default bg-neutral-primary-soft p-2">
                    <option value="">Semua</option><option value="Gym">Gym</option><option value="Minuman">Minuman</option>
                </select>
            </label>
            <label class="text-sm text-heading">Jenis
                <select wire:model.live="kind" class="mt-1 block w-full rounded-md border border-default bg-neutral-primary-soft p-2">
                    <option value="">Semua</option><option value="Operasional">Operasional</option><option value="Pengeluaran">Pengeluaran</option>
                </select>
            </label>
            <label class="text-sm text-heading">Shift
                <select wire:model.live="shift" class="mt-1 block w-full rounded-md border border-default bg-neutral-primary-soft p-2">
                    <option value="">Semua Shift</option>
                    @foreach ($this->shiftOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>
        </div>
        @if ($errors->any())
            <ul role="alert" class="mt-3 text-sm text-red-600">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        @endif
        <p wire:loading role="status" class="mt-3 text-sm text-body">Memuat laporan...</p>
    </div>

    @if ($this->report)
        @php
            $report = $this->report;
            $summary = $this->summary;
            $rows = $this->rows;
        @endphp
        <section class="min-w-0 rounded-md border border-default bg-neutral-primary-soft">
            <div class="border-b border-default p-4">
                <h2 class="font-semibold text-heading">Ringkasan</h2>
                <p class="text-sm text-body">{{ \Carbon\Carbon::parse($report->startDate)->format('d/m/Y') }} – {{ \Carbon\Carbon::parse($report->endDate)->format('d/m/Y') }}</p>
            </div>
            <div class="overflow-hidden">
                <table data-responsive-table data-responsive-breakpoint="sm" class="table-fixed w-full text-left text-sm text-body">
                    <thead class="bg-neutral-secondary-soft text-heading"><tr><th class="px-4 py-3">Sumber</th><th class="px-4 py-3 text-right">Nilai Operasional</th><th class="px-4 py-3 text-right">Pengeluaran</th></tr></thead>
                    <tbody>
                        @foreach ($summary as $label => $totals)
                            <tr wire:key="summary-{{ $label }}" @class(['border-t border-default', 'font-bold text-heading' => $label === 'Total'])>
                                <td class="px-4 py-3">{{ $label }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-right">Rp {{ number_format($totals['operasional'], 0, ',', '.') }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-right">Rp {{ number_format($totals['pengeluaran'], 0, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="border-t border-default bg-neutral-secondary-soft font-bold text-heading">
                            <td class="px-4 py-3">Total Operasional dan Pengeluaran</td>
                            <td colspan="2" class="px-4 py-3 text-right">Rp {{ number_format($summary['Total']['operasional'] + $summary['Total']['pengeluaran'], 0, ',', '.') }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </section>
        <section class="min-w-0 rounded-md border border-default bg-neutral-primary-soft">
            <h2 class="border-b border-default p-4 font-semibold text-heading">Rincian Transaksi <span class="font-normal text-body">({{ $rows->total() }})</span></h2>
            <div class="overflow-hidden">
                <table data-responsive-table data-responsive-breakpoint="lg" class="table-fixed w-full text-left text-sm text-body">
                    <thead class="bg-neutral-secondary-soft text-heading"><tr>
                        @foreach (['Tanggal', 'Sumber', 'Jenis', 'Referensi', 'Uraian', 'Pencatat / Staf', 'Shift', 'Nominal'] as $heading)
                            <th @class(['px-4 py-3 whitespace-nowrap', 'text-right' => $heading === 'Nominal'])>{{ $heading }}</th>
                        @endforeach
                    </tr></thead>
                    <tbody>
                        @forelse ($rows as $row)
                            <tr wire:key="{{ $row->record_source }}-{{ $row->record_id }}" class="border-t border-default">
                                <td class="whitespace-nowrap px-4 py-3">{{ \Carbon\Carbon::parse($row->occurred_at)->format('d/m/Y') }}</td>
                                <td class="px-4 py-3">{{ $row->source }}</td><td class="px-4 py-3">{{ $row->kind }}</td>
                                <td class="px-4 py-3">{{ $row->reference ?: '—' }}</td><td class="px-4 py-3">{{ $row->description ?: '—' }}</td>
                                <td class="px-4 py-3">{{ $row->staff ?: '—' }}</td><td class="px-4 py-3">{{ $row->shift ?: '—' }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-right">Rp {{ number_format($row->amount, 0, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="px-4 py-8 text-center">Tidak ada data operasional atau pengeluaran sesuai filter.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="p-4">{{ $rows->links() }}</div>
        </section>
    @endif
</div>
