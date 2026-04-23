<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use Livewire\WithPagination;
use App\Models\User;
use App\Models\Membership;
use Carbon\Carbon;
use App\Exports\RekapBonusExport;
use Maatwebsite\Excel\Facades\Excel;

new #[Layout('layouts::admin')] class extends Component 
{
    
    use WithPagination;

    public User $staffUser; // Variabel untuk menyimpan data user (Admin/Sales)
    public $search = '';
    public $filterTime = 'month'; // Default bulan ini
    public $startDate;
    public $endDate;

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

    public function updatingSearch()
    {
        $this->resetPage();
    }

    private function getBaseQuery()
    {
        return Membership::where(function ($query) {
                $query->where('follow_up_id', $this->staffUser->id)
                      ->orWhere('follow_up_id_two', $this->staffUser->id);
            })
            ->where('type', '!=', 'visit')
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
                $query->whereHas('transactions', function ($q) {
                    $q->whereBetween('payment_date', [
                        $this->startDate . ' 00:00:00',
                        $this->endDate . ' 23:59:59'
                    ]);
                });
            });
    }

    #[Computed]
    public function memberships()
    {
        return $this->getBaseQuery()
            ->with(['user', 'followUp', 'followUpTwo', 'gymPackage', 'ptPackage'])
            ->latest('start_date')
            ->paginate(500);
    }

    #[Computed]
    public function totalNominalAkhir()
    {
        // Tambahkan 'follow_up_id' ke dalam select agar bisa dibandingkan
        $memberships = $this->getBaseQuery()->get(['total_paid', 'follow_up_id', 'follow_up_id_two']);
        
        $total = 0;
        foreach ($memberships as $membership) {
            $nominal = $membership->total_paid ?? 0;
            
            // Logika Pembagian: Bagi 2 HANYA JIKA kedua form terisi dengan orang yang BERBEDA
            if ($membership->follow_up_id && $membership->follow_up_id_two && ($membership->follow_up_id !== $membership->follow_up_id_two)) {
                $nominalAkhir = $nominal / 2;
            } else {
                $nominalAkhir = $nominal; // Jika hanya 1 yang diisi atau keduanya orang yang SAMA, dapat FULL
            }
            
            $total += $nominalAkhir;
        }

        return $total;
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

    <div class="relative overflow-x-auto bg-neutral-primary-soft shadow-xs rounded-md border border-default mb-6">
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
        
        <table class="w-full text-sm text-left rtl:text-right text-body">
            <thead class="text-xs text-body bg-neutral-secondary-medium border-b border-default-medium">
                <tr>
                    <th rowspan="3" class="px-6 py-3 font-medium align-middle border border-default-medium">No</th>
                    <th class="px-6 py-3 font-medium text-center border border-default-medium">Tgl Mulai</th>
                    <th class="px-6 py-3 font-medium text-center border border-default-medium">Tgl Selesai</th>
                    <th rowspan="3" class="px-6 py-3 font-medium align-middle border border-default-medium">Paket Membership</th>
                    <th rowspan="3" class="px-6 py-3 font-medium align-middle border border-default-medium">Nama Member</th>
                    <th rowspan="3" class="px-6 py-3 font-medium text-right align-middle border border-default-medium">Nominal</th>
                    <th rowspan="3" class="px-6 py-3 font-medium text-right align-middle border border-default-medium">Nominal Akhir</th>
                    <th rowspan="3" class="px-6 py-3 font-medium align-middle border border-default-medium">Follow Up 1</th>
                    <th rowspan="3" class="px-6 py-3 font-medium align-middle border border-default-medium">Follow Up 2</th>
                </tr>
                <tr>
                    <th colspan="2" class="px-6 py-3 font-medium text-center border border-default-medium">MEMBERSHIP</th>
                </tr>
                <tr>
                    <th class="px-6 py-3 font-medium text-center border border-default-medium">SALES ADMIN</th>
                    <th class="px-6 py-3 font-medium text-center uppercase border border-default-medium">{{ $staffUser->name }}</th>
                </tr>
            </thead>
            <tbody>
               @forelse ($this->memberships as $membership)
                    @php
                        // Menentukan nama paket (transaction_type + package_name)
                        $packageName = trim(($membership->transaction_type ?? '') . ' ' . ($membership->package_name ?? ''));
                        
                        // Menentukan Nominal Akhir
                        $nominal = $membership->total_paid ?? 0;
                        
                        // Logika Pembagian yang sama dengan yang di atas
                        if ($membership->follow_up_id && $membership->follow_up_id_two && ($membership->follow_up_id !== $membership->follow_up_id_two)) {
                            $nominalAkhir = $nominal / 2;
                        } else {
                            $nominalAkhir = $nominal;
                        }
                    @endphp
                    
                    <tr wire:key="{{ $membership->id }}" class="bg-white border-b border-gray-100 hover:bg-gray-50">
                        <td class="px-6 py-4">{{ $loop->iteration + ($this->memberships->currentPage() - 1) * $this->memberships->perPage() }}</td>
                        
                        
                        
                        <td class="px-6 py-4 whitespace-nowrap">
                            {{ $membership->start_date ? \Carbon\Carbon::parse($membership->start_date)->translatedFormat('l, d F Y') : 'BELUM AKTIF' }}
                        </td>
                        
                        <td class="px-6 py-4 whitespace-nowrap">
                            @php
                                $endDate = $membership->type === 'pt' ? $membership->pt_end_date : $membership->membership_end_date;
                            @endphp
                            {{ $endDate ? \Carbon\Carbon::parse($endDate)->translatedFormat('l, d F Y') : 'BELUM AKTIF' }}
                        </td>
                        
                        <td class="px-6 py-4 font-medium text-gray-700 whitespace-nowrap">
                            <span class="px-2 py-0.5 text-xs rounded border border-gray-200 bg-gray-50 shadow-xs uppercase">
                                {{ $packageName }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap font-bold text-gray-800">
                            {{ $membership->user->name ?? '-' }}
                        </td>
                        
                        <td class="px-6 py-4 text-right whitespace-nowrap text-gray-600">
                            Rp {{ number_format($nominal, 0, ',', '.') }}
                        </td>
                        
                        <td class="px-6 py-4 text-right font-bold text-emerald-600 whitespace-nowrap">
                            Rp {{ number_format($nominalAkhir, 0, ',', '.') }}
                        </td>
                        
                        <td class="px-6 py-4 whitespace-nowrap">
                            {{ $membership->followUp->name ?? '-' }}
                        </td>
                        
                        <td class="px-6 py-4 whitespace-nowrap">
                            {{ $membership->followUpTwo->name ?? '-' }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="px-6 py-8 text-center text-gray-500">
                            Belum ada riwayat bonus untuk rentang waktu ini.
                        </td>
                    </tr>
                @endforelse
            </tbody>
            @if($this->memberships->count() > 0)
                <tfoot class="bg-gray-100 font-semibold text-gray-900 border-t-2 border-gray-300">
                    <tr>
                        <td colspan="6" class="px-6 py-4 text-right">
                            Total Keseluruhan:
                        </td>
                        <td class="px-6 py-4 text-right text-emerald-700 whitespace-nowrap">
                            Rp {{ number_format($this->totalNominalAkhir, 0, ',', '.') }}
                        </td>
                        <td colspan="2" class="px-6 py-4"></td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
    <div class="mb-6">
        {{ $this->memberships->links() }}
    </div>
</div>