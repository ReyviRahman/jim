<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use Livewire\WithPagination;
use App\Models\User;
use App\Models\Membership;
use App\Models\SalesKonsultan;
use App\Models\KasirKonsultan;
use App\Models\CoachKonsultan;
use Carbon\Carbon;
use App\Exports\RekapBonusExport;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Facades\Excel;

new #[Layout('layouts::admin')] class extends Component 
{
    
    use WithPagination;

    public User $staffUser; // Variabel untuk menyimpan data user (Admin/Sales)
    public $search = '';
    public $filterTime = 'month'; // Default bulan ini
    public $startDate;
    public $endDate;

    public $sortBy = 'transactions_max_payment_date';
    public $sortDirection = 'desc';

    public function sort($column)
    {
        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = 'asc';
        }

        $this->resetPage();
    }

    public function mount(User $user)
    {
        $this->staffUser = $user;
        $this->setFilterTime('month'); // Set default tanggal saat komponen diload
    }

    public function setFilterTime($time)
    {
        $this->filterTime = $time;

        switch ($time) {
            case 'today':
                $this->startDate = Carbon::today()->toDateString();
                $this->endDate = Carbon::today()->toDateString();
                break;
            case 'week':
                $this->startDate = Carbon::now()->startOfWeek()->toDateString();
                $this->endDate = Carbon::now()->endOfWeek()->toDateString();
                break;
            case 'month':
                $this->startDate = Carbon::now()->startOfMonth()->toDateString();
                $this->endDate = Carbon::now()->endOfMonth()->toDateString();
                break;
        }

        $this->resetPage(); // Reset paginasi setiap kali filter berubah
    }

    public function setDateRange($dateStr)
    {
        // Jika input kosong (user menghapus tanggal), hentikan fungsi
        if (empty($dateStr)) {
            return;
        }

        // Cek apakah ada kata ' to ' (berarti memilih rentang tanggal)
        if (str_contains($dateStr, ' to ')) {
            $dates = explode(' to ', $dateStr);
            $this->startDate = $dates[0];
            $this->endDate = $dates[1];
        } else {
            // Jika tidak ada ' to ', berarti user double-click 1 tanggal saja
            // Maka startDate dan endDate disamakan ke tanggal tersebut
            $this->startDate = $dateStr;
            $this->endDate = $dateStr;
        }

        $this->filterTime = 'custom';
        $this->resetPage();
    }

    public function updatingSortBy()
    {
        $this->resetPage();
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingSortDirection()
    {
        $this->resetPage();
    }

    private function applyLatestPaymentDateFilter(Builder $query): Builder
    {
        return $query
            ->whereHas('transactions', function (Builder $query): void {
                $query->whereBetween('payment_date', [
                    $this->startDate,
                    $this->endDate,
                ]);
            })
            ->whereDoesntHave('transactions', function (Builder $query): void {
                $query->where('payment_date', '>', $this->endDate);
            });
    }

    private function getBaseQuery(): Builder
    {
        return Membership::where(function ($query) {
                $query->where('follow_up_id', $this->staffUser->id)
                      ->orWhere('follow_up_id_two', $this->staffUser->id);
            })
            ->where('type', '!=', 'visit')
            ->when(
                $this->staffUser->role === 'pt',
                fn (Builder $query): Builder => $query->where('type', 'pt')
            )
            ->where('payment_status', 'paid')
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->whereHas('members', function ($sub) {
                        $sub->where('name', 'like', '%' . $this->search . '%');
                    })
                    ->orWhereHas('user', function ($sub) {
                        $sub->where('name', 'like', '%' . $this->search . '%');
                    });
                });
            })
            ->when($this->startDate && $this->endDate, function ($query) {
                $this->applyLatestPaymentDateFilter($query);
            });
    }

    #[Computed]
    public function memberships()
    {
        $query = $this->getBaseQuery()
            ->with([
                'user',
                'followUp',
                'followUpTwo',
                'gymPackage',
                'ptPackage',
                'transactions',
            ])
            ->withMax('transactions', 'payment_date');

        if ($this->sortBy === 'user_name') {
            $query->join('users', 'memberships.user_id', '=', 'users.id')
                ->orderBy('users.name', $this->sortDirection)
                ->select('memberships.*');
        } elseif ($this->sortBy === 'package_name') {
            $query->orderBy('transaction_type', $this->sortDirection)
                ->orderBy('package_name', $this->sortDirection);
        } else {
            $query->orderBy('transactions_max_payment_date', $this->sortDirection);
        }

        return $query->paginate(500);
    }

    #[Computed]
    public function totalNominalAkhir()
    {
        $memberships = $this->getBaseQuery()
            ->with('followUp:id,role')
            ->get(['total_paid', 'follow_up_id', 'follow_up_id_two', 'price_paid', 'normal_price', 'net_price', 'unrecommended_price']);

        $total = 0;
        foreach ($memberships as $membership) {
            $total += $membership->calculateNominalAkhir();
        }

        return $total;
    }

    #[Computed]
    public function bonusInfo(): array
    {
        $total = $this->totalNominalAkhir;

        $range = match ($this->staffUser->role) {
            'kasir_gym' => KasirKonsultan::findByNominal($total),
            'sales' => SalesKonsultan::findByNominal($total),
            'pt' => CoachKonsultan::findByNominal($total),
            default => null,
        };

        if (! $range) {
            return [
                'rentang_satu' => null,
                'rentang_dua' => null,
                'persen' => 0,
                'total_bonus' => 0,
            ];
        }

        $persen = (float) $range->persen;
        $totalBonus = $total * ($persen / 100);

        return [
            'rentang_satu' => $range->rentang_satu,
            'rentang_dua' => $range->rentang_dua,
            'persen' => $persen,
            'total_bonus' => $totalBonus,
        ];
    }

    public function exportExcel()
    {
        // Beri nama file yang dinamis berdasarkan nama staff dan tanggal download
        $fileName = 'Rekap_Bonus_' . str_replace(' ', '_', $this->staffUser->name) . '_' . '.xlsx';
        
        // Panggil Export Class dengan mengirimkan parameter filter yang sedang aktif
        return Excel::download(
            new RekapBonusExport($this->staffUser->id, $this->search, $this->startDate, $this->endDate), 
            $fileName
        );
    }

    
};
?>

<div>
    <div class="flex sm:flex-row flex-col justify-between items-center mb-6">
        <h5 class="text-xl font-semibold text-heading">
            Perhitungan Bonus {{ $staffUser->name }} Target 
            @if($startDate && $endDate)
                @if($startDate === $endDate)
                    {{ \Carbon\Carbon::parse($startDate)->translatedFormat('d F Y') }}
                @else
                    {{ \Carbon\Carbon::parse($startDate)->translatedFormat('d F Y') }} - {{ \Carbon\Carbon::parse($endDate)->translatedFormat('d F Y') }}
                @endif
            @else
                -
            @endif
        </h5>
        <div class="flex gap-2">
            <button wire:click="exportExcel" wire:loading.attr="disabled" class="inline-flex items-center justify-center text-white bg-emerald-600 border border-transparent hover:bg-emerald-700 focus:ring-4 focus:ring-emerald-300 shadow-xs font-medium rounded-md text-sm px-4 py-2.5 focus:outline-none disabled:opacity-50">
                <svg class="w-4 h-4 me-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3M3 17V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z"></path></svg>
                <span wire:loading.remove wire:target="exportExcel">Export Excel</span>
                <span wire:loading wire:target="exportExcel">Memproses...</span>
            </button>
        </div>
    </div>

    <div class="relative bg-neutral-primary-soft shadow-xs rounded-md border border-default mb-6">
        <div class="p-4 flex flex-col lg:flex-row items-center justify-between gap-4">
            
            {{-- Search --}}
            <div class="relative w-full lg:w-auto flex-1">
                <div class="absolute inset-y-0 start-0 flex items-center ps-3 pointer-events-none">
                    <svg class="w-4 h-4 text-body" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-width="2" d="m21 21-3.5-3.5M17 10a7 7 0 1 1-14 0 7 7 0 0 1 14 0Z"/></svg>
                </div>
                <input type="text" wire:model.live.debounce.300ms="search" class="block w-full ps-9 pe-3 py-2.5 bg-white border border-default-medium text-heading text-sm rounded-md focus:ring-brand focus:border-brand shadow-xs" placeholder="Cari nama member...">
            </div>
            
            {{-- Filters --}}
            <div class="flex flex-wrap items-center gap-3 w-full lg:w-auto">
                {{-- Datepicker Custom --}}
                <div class="relative w-full sm:w-56" wire:ignore>
                    <div class="absolute inset-y-0 start-0 flex items-center ps-3 pointer-events-none">
                        <svg class="w-4 h-4 text-body" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 10h16M8 14h8m-4-7V4M7 7V4m10 3V4M5 20h14a1 1 0 0 0 1-1V7a1 1 0 0 0-1-1H5a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1Z"/></svg>
                    </div>
                    <input type="text" x-data x-init="flatpickr($el, { mode: 'range', dateFormat: 'Y-m-d', placeholder: 'Pilih Tanggal', onClose: function(selectedDates, dateStr) { $wire.setDateRange(dateStr) } })" class="block w-full ps-9 pe-3 py-2.5 bg-white border border-default-medium text-heading text-sm rounded-md focus:ring-brand focus:border-brand shadow-xs" placeholder="Pilih Rentang Tanggal">
                </div>

                {{-- Filter Presets --}}
                <div x-data="{ open: false }" class="relative">
                    <button @click="open = !open" @click.outside="open = false" class="inline-flex items-center justify-center text-body bg-white border border-default-medium hover:bg-gray-50 focus:ring-4 focus:ring-gray-100 shadow-xs font-medium rounded-md text-sm px-3 py-2.5" type="button">
                        <svg class="w-4 h-4 me-1.5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-width="2" d="M18.796 4H5.204a1 1 0 0 0-.753 1.659l5.302 6.058a1 1 0 0 1 .247.659v4.874a.5.5 0 0 0 .2.4l3 2.25a.5.5 0 0 0 .8-.4v-7.124a1 1 0 0 1 .247-.659l5.302-6.059c.566-.646.106-1.658-.753-1.658Z"/></svg>
                        @if($filterTime === 'today') Hari Ini
                        @elseif($filterTime === 'week') Minggu Ini
                        @elseif($filterTime === 'month') Bulan Ini
                        @elseif($filterTime === 'custom') Kustom @endif
                        <svg class="w-4 h-4 ms-1.5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m19 9-7 7-7-7"/></svg>
                    </button>
                    
                    <div x-show="open" style="display: none;" class="absolute right-0 z-50 mt-2 bg-white border border-gray-200 rounded-md shadow-lg w-40">
                        <ul class="p-2 text-sm text-gray-700 font-medium">
                            <li><button type="button" wire:click="setFilterTime('today')" @click="open = false" class="w-full text-left p-2 hover:bg-gray-100 rounded">Hari ini</button></li>
                            <li><button type="button" wire:click="setFilterTime('week')" @click="open = false" class="w-full text-left p-2 hover:bg-gray-100 rounded">Minggu ini</button></li>
                            <li><button type="button" wire:click="setFilterTime('month')" @click="open = false" class="w-full text-left p-2 hover:bg-gray-100 rounded">Bulan ini</button></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="border-t border-default-medium p-3 xl:hidden">
            <div class="flex flex-wrap items-center gap-2 text-xs">
                <span class="font-medium text-gray-500">Urutkan:</span>
                <button type="button" wire:click="sort('user_name')" class="rounded-md border px-2.5 py-1.5 font-medium {{ $sortBy === 'user_name' ? 'border-brand bg-brand text-[#34342F]' : 'border-default-medium bg-white text-body' }}">
                    Nama Member
                    @if($sortBy === 'user_name')
                        {{ $sortDirection === 'asc' ? '▲' : '▼' }}
                    @endif
                </button>
                <button type="button" wire:click="sort('package_name')" class="rounded-md border px-2.5 py-1.5 font-medium {{ $sortBy === 'package_name' ? 'border-brand bg-brand text-[#34342F]' : 'border-default-medium bg-white text-body' }}">
                    Paket Membership
                    @if($sortBy === 'package_name')
                        {{ $sortDirection === 'asc' ? '▲' : '▼' }}
                    @endif
                </button>
            </div>
        </div>

        <div class="overflow-hidden">
        <table class="block w-full table-fixed text-left text-xs leading-tight text-body xl:table">
            <colgroup class="hidden xl:table-column-group">
                <col class="w-[3%]">
                <col class="w-[12%]">
                <col class="w-[12%]">
                <col class="w-[10%]">
                <col class="w-[10%]">
                <col class="w-[9%]">
                <col class="w-[9%]">
                <col class="w-[10%]">
                <col class="w-[10%]">
                <col class="w-[9%]">
                <col class="w-[6%]">
            </colgroup>
            <thead class="hidden border-b border-default-medium bg-neutral-secondary-medium text-[10px] text-body xl:table-header-group 2xl:text-xs">
                <tr>
                    <th rowspan="3" class="break-words border border-default-medium px-1.5 py-2 align-middle font-medium">No</th>
                    <th rowspan="3" wire:click="sort('user_name')" class="cursor-pointer break-words border border-default-medium px-1.5 py-2 align-middle font-medium select-none hover:bg-gray-200">
                        Nama Member
                        @if($sortBy === 'user_name')
                            <span class="ml-1">{{ $sortDirection === 'asc' ? '▲' : '▼' }}</span>
                        @endif
                    </th>
                    <th rowspan="3" wire:click="sort('package_name')" class="cursor-pointer break-words border border-default-medium px-1.5 py-2 align-middle font-medium select-none hover:bg-gray-200">
                        Paket Membership
                        @if($sortBy === 'package_name')
                            <span class="ml-1">{{ $sortDirection === 'asc' ? '▲' : '▼' }}</span>
                        @endif
                    </th>
                    <th rowspan="3" class="break-words border border-default-medium px-1.5 py-2 text-right align-middle font-medium">Nominal</th>
                    <th rowspan="3" class="break-words border border-default-medium px-1.5 py-2 text-right align-middle font-medium">Nominal Akhir</th>
                    <th rowspan="3" class="break-words border border-default-medium px-1.5 py-2 align-middle font-medium">Follow Up 1</th>
                    <th rowspan="3" class="break-words border border-default-medium px-1.5 py-2 align-middle font-medium">Follow Up 2</th>
                    <th class="break-words border border-default-medium px-1.5 py-2 text-center font-medium">Tgl Mulai</th>
                    <th class="break-words border border-default-medium px-1.5 py-2 text-center font-medium">Tgl Selesai</th>
                    <th rowspan="3" class="break-words border border-default-medium px-1.5 py-2 align-middle font-medium">Tgl Bayar</th>
                    <th rowspan="3" class="break-words border border-default-medium px-1.5 py-2 align-middle font-medium">Aksi</th>
                </tr>
                <tr>
                    <th colspan="2" class="break-words border border-default-medium px-1.5 py-2 text-center font-medium">MEMBERSHIP</th>
                </tr>
                <tr>
                    <th class="break-words border border-default-medium px-1.5 py-2 text-center font-medium">SALES ADMIN</th>
                    <th class="break-words border border-default-medium px-1.5 py-2 text-center font-medium uppercase">{{ $staffUser->name }}</th>
                </tr>
            </thead>
            <tbody class="grid gap-3 bg-gray-50 p-3 xl:table-row-group xl:bg-transparent xl:p-0">
               @forelse ($this->memberships as $membership)
                    @php
                        // Menentukan nama paket (transaction_type + package_name)
                        $packageName = trim(($membership->transaction_type ?? '') . ' ' . ($membership->package_name ?? ''));

                        $nominal = $membership->total_paid ?? 0;
                        $nominalAkhir = $membership->calculateNominalAkhir();
                    @endphp
                    
                    <tr wire:key="{{ $membership->id }}" class="grid grid-cols-1 overflow-hidden rounded-md border border-gray-200 bg-white shadow-xs sm:grid-cols-2 xl:table-row xl:rounded-none xl:border-x-0 xl:border-t-0 xl:shadow-none xl:hover:bg-gray-50">
                        <td class="flex min-w-0 items-start justify-between gap-4 border-b border-gray-100 px-3 py-2 sm:block xl:table-cell xl:border-0 xl:px-1.5 xl:py-2 xl:align-top">
                            <span class="font-medium text-gray-500 xl:hidden">No</span>
                            <span class="min-w-0 break-words text-right sm:block sm:text-left xl:text-left">{{ $loop->iteration + ($this->memberships->currentPage() - 1) * $this->memberships->perPage() }}</span>
                        </td>
                        <td class="flex min-w-0 items-start justify-between gap-4 border-b border-gray-100 px-3 py-2 sm:block xl:table-cell xl:border-0 xl:px-1.5 xl:py-2 xl:align-top">
                            <span class="font-medium text-gray-500 xl:hidden">Nama Member</span>
                            <span class="min-w-0 break-words text-right font-bold text-gray-800 sm:block sm:text-left xl:text-left">
                                {{ $membership->user->name ?? '-' }}
                            </span>
                        </td>
                        <td class="flex min-w-0 items-start justify-between gap-4 border-b border-gray-100 px-3 py-2 sm:block xl:table-cell xl:border-0 xl:px-1.5 xl:py-2 xl:align-top">
                            <span class="font-medium text-gray-500 xl:hidden">Paket Membership</span>
                            <span class="min-w-0 break-words text-right sm:block sm:text-left xl:text-left">
                                <span class="inline-block max-w-full break-words rounded border border-gray-200 bg-gray-50 px-1.5 py-0.5 text-[10px] font-medium text-gray-700 uppercase shadow-xs">
                                    {{ $packageName }}
                                </span>
                            </span>
                        </td>
                        <td class="flex min-w-0 items-start justify-between gap-4 border-b border-gray-100 px-3 py-2 text-right sm:block xl:table-cell xl:border-0 xl:px-1.5 xl:py-2 xl:align-top">
                            <span class="font-medium text-gray-500 xl:hidden">Nominal</span>
                            <span class="min-w-0 break-words sm:block">Rp {{ number_format($nominal, 0, ',', '.') }}</span>

                            @if(auth()->check() && auth()->user()->role === 'admin')
                                @php
                                    $priceLabelData = $membership->getPriceLabel();
                                @endphp

                                @if($priceLabelData)
                                    <div class="mt-1 flex justify-end sm:justify-start xl:justify-end">
                                        <span class="break-words rounded-full px-1.5 py-0.5 text-[9px] font-semibold {{ $priceLabelData['color'] }}">
                                            {{ $priceLabelData['label'] }}
                                        </span>
                                    </div>
                                @endif
                            @endif
                        </td>
                        <td class="flex min-w-0 items-start justify-between gap-4 border-b border-gray-100 px-3 py-2 text-right sm:block xl:table-cell xl:border-0 xl:px-1.5 xl:py-2 xl:align-top">
                            <span class="font-medium text-gray-500 xl:hidden">Nominal Akhir</span>
                            <span class="min-w-0 break-words font-bold text-emerald-600 sm:block">Rp {{ number_format($nominalAkhir, 0, ',', '.') }}</span>
                        </td>
                        <td class="flex min-w-0 items-start justify-between gap-4 border-b border-gray-100 px-3 py-2 sm:block xl:table-cell xl:border-0 xl:px-1.5 xl:py-2 xl:align-top">
                            <span class="font-medium text-gray-500 xl:hidden">Follow Up 1</span>
                            <span class="min-w-0 break-words text-right sm:block sm:text-left xl:text-left">{{ $membership->followUp->name ?? '-' }}</span>
                        </td>
                        <td class="flex min-w-0 items-start justify-between gap-4 border-b border-gray-100 px-3 py-2 sm:block xl:table-cell xl:border-0 xl:px-1.5 xl:py-2 xl:align-top">
                            <span class="font-medium text-gray-500 xl:hidden">Follow Up 2</span>
                            <span class="min-w-0 break-words text-right sm:block sm:text-left xl:text-left">{{ $membership->followUpTwo->name ?? '-' }}</span>
                        </td>
                        <td class="flex min-w-0 items-start justify-between gap-4 border-b border-gray-100 px-3 py-2 sm:block xl:table-cell xl:border-0 xl:px-1.5 xl:py-2 xl:align-top">
                            <span class="font-medium text-gray-500 xl:hidden">Tgl Mulai</span>
                            <span class="min-w-0 break-words text-right sm:block sm:text-left xl:text-left">{{ $membership->start_date ? \Carbon\Carbon::parse($membership->start_date)->translatedFormat('l, d F Y') : 'BELUM AKTIF' }}</span>
                        </td>

                        <td class="flex min-w-0 items-start justify-between gap-4 border-b border-gray-100 px-3 py-2 sm:block xl:table-cell xl:border-0 xl:px-1.5 xl:py-2 xl:align-top">
                            @php
                                $endDate = $membership->type === 'pt' ? $membership->pt_end_date : $membership->membership_end_date;
                            @endphp
                            <span class="font-medium text-gray-500 xl:hidden">Tgl Selesai</span>
                            <span class="min-w-0 break-words text-right sm:block sm:text-left xl:text-left">{{ $endDate ? \Carbon\Carbon::parse($endDate)->translatedFormat('l, d F Y') : 'BELUM AKTIF' }}</span>
                        </td>

                        <td class="flex min-w-0 items-start justify-between gap-4 border-b border-gray-100 px-3 py-2 sm:block xl:table-cell xl:border-0 xl:px-1.5 xl:py-2 xl:align-top">
                            <span class="font-medium text-gray-500 xl:hidden">Tgl Bayar</span>
                            <span class="min-w-0 break-words text-right font-semibold text-yellow-500 sm:block sm:text-left xl:text-left">{{ $membership->transactions->sortByDesc('payment_date')->first()?->payment_date?->translatedFormat('d F Y') ?? '-' }}</span>
                        </td>

                        <td class="flex min-w-0 items-start justify-between gap-4 px-3 py-2 sm:block xl:table-cell xl:px-1.5 xl:py-2 xl:align-top">
                            <span class="font-medium text-gray-500 xl:hidden">Aksi</span>
                            @if(auth()->user()->role === 'admin')
                                <a href="{{ route('admin.membership.edit', $membership->id) }}?redirect_to={{ urlencode('admin.rekap-bonus.detail') }}&redirect_id={{ $staffUser->id }}" class="inline-flex items-center break-words text-right text-xs font-medium text-brand hover:text-brand-dark sm:text-left">
                                    <svg class="me-1 h-3.5 w-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10"></path></svg>
                                    Edit
                                </a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr class="block xl:table-row">
                        <td colspan="11" class="block px-3 py-8 text-center text-gray-500 xl:table-cell">
                            Belum ada riwayat bonus untuk rentang waktu ini.
                        </td>
                    </tr>
                @endforelse
            </tbody>
            @if($this->memberships->count() > 0)
            <tfoot class="block border-t-2 border-gray-300 bg-gray-100 font-semibold text-gray-900 xl:table-footer-group">
                    <tr class="grid grid-cols-1 xl:table-row">
                        <td colspan="4" class="block px-3 pt-3 text-left xl:table-cell xl:px-1.5 xl:py-2 xl:text-right">
                            Total Keseluruhan:
                        </td>
                        <td class="block break-words px-3 pb-3 text-left text-emerald-700 xl:table-cell xl:px-1.5 xl:py-2 xl:text-right">
                            Rp {{ number_format($this->totalNominalAkhir, 0, ',', '.') }}
                        </td>
                        <td colspan="6" class="hidden px-1.5 py-2 xl:table-cell"></td>
                    </tr>
                    @if(in_array(auth()->user()->role, ['admin', 'head_coach']))
                        @php
                            $bonus = $this->bonusInfo;
                        @endphp
                        @if($bonus['persen'] > 0)
                            <tr class="grid grid-cols-1 border-t border-gray-200 xl:table-row">
                                <td colspan="4" class="block px-3 pt-3 text-left text-gray-600 xl:table-cell xl:px-1.5 xl:py-2 xl:text-right">
                                    Bonus ({{ $bonus['persen'] }}%)
                                    <span class="block break-words text-[10px] text-gray-400">
                                        Rentang:
                                        @if(strtolower($bonus['rentang_satu']) === 'min')
                                            ≤ Rp {{ number_format((float) $bonus['rentang_dua'], 0, ',', '.') }}
                                        @elseif(strtolower($bonus['rentang_dua']) === 'plus')
                                            ≥ Rp {{ number_format((float) $bonus['rentang_satu'], 0, ',', '.') }}
                                        @else
                                            Rp {{ number_format((float) $bonus['rentang_satu'], 0, ',', '.') }} - Rp {{ number_format((float) $bonus['rentang_dua'], 0, ',', '.') }}
                                        @endif
                                    </span>
                                </td>
                                <td class="block break-words px-3 pb-3 text-left text-blue-700 xl:table-cell xl:px-1.5 xl:py-2 xl:text-right">
                                    Rp {{ number_format($bonus['total_bonus'], 0, ',', '.') }}
                                </td>
                                <td colspan="6" class="hidden px-1.5 py-2 xl:table-cell"></td>
                            </tr>
                        @else
                            <tr class="grid grid-cols-1 border-t border-gray-200 xl:table-row">
                                <td colspan="4" class="block px-3 pt-3 text-left text-gray-500 xl:table-cell xl:px-1.5 xl:py-2 xl:text-right">
                                    Tidak ada rentang bonus yang cocok
                                </td>
                                <td class="block break-words px-3 pb-3 text-left text-gray-400 xl:table-cell xl:px-1.5 xl:py-2 xl:text-right">
                                    Rp 0
                                </td>
                                <td colspan="6" class="hidden px-1.5 py-2 xl:table-cell"></td>
                            </tr>
                        @endif
                    @endif
                </tfoot>
            @endif
        </table>
        </div>
    </div>
    <div class="mb-6">
        {{ $this->memberships->links() }}
    </div>
</div>
