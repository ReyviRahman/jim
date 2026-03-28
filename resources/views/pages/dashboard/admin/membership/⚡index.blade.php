<?php

namespace App\Livewire\Admin; 

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed; 
use App\Models\Membership;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel; 
use App\Exports\MembershipExport;

new #[Layout('layouts::admin')] class extends Component
{
    use WithPagination;

    // --- VARIABEL UNTUK FILTER & PENCARIAN ---
    public $search = '';
    public $filterTime = 'all'; // all, today, week, month, custom
    public $dateStart = null;
    public $dateEnd = null;

    // Reset halaman ke 1 setiap kali user mengetik pencarian
    public function updatingSearch()
    {
        $this->resetPage();
    }

    // Fungsi untuk Dropdown Waktu Cepat
    public function setFilterTime($time)
    {
        $this->filterTime = $time;
        $this->dateStart = null;
        $this->dateEnd = null;
        $this->resetPage();
    }

    // Fungsi yang dipanggil oleh Flatpickr saat tanggal dipilih
    public function setDateRange($rangeStr)
    {
        if (str_contains($rangeStr, ' to ')) {
            $dates = explode(' to ', $rangeStr);
            $this->dateStart = $dates[0];
            $this->dateEnd = $dates[1];
            $this->filterTime = 'custom';
        } elseif ($rangeStr) {
            $this->dateStart = $rangeStr;
            $this->dateEnd = $rangeStr;
            $this->filterTime = 'custom';
        } else {
            $this->filterTime = 'all';
            $this->dateStart = null;
            $this->dateEnd = null;
        }
        $this->resetPage();
    }

    public function approve($membershipId)
    {
        $membership = Membership::findOrFail($membershipId);
        
        if ($membership->status === 'pending') {
            $membership->update([
                'status' => 'active'
            ]);
            
            session()->flash('success', "Membership berhasil diaktifkan!");
        } else {
            session()->flash('error', "Gagal: Membership ini tidak dalam status pending.");
        }
    }

    public function reject($membershipId)
    {
        $membership = Membership::findOrFail($membershipId);
        
        if ($membership->status === 'pending') {
            $membership->update([
                'status' => 'rejected'
            ]);

            session()->flash('success', "Pengajuan membership berhasil ditolak.");
        } else {
            session()->flash('error', "Gagal: Membership ini tidak dalam status pending.");
        }
    }

    #[Computed]
    public function memberships()
    {
        $query = Membership::with(['user', 'members', 'admin', 'followUp', 'followUpTwo', 'personalTrainer', 'gymPackage', 'ptPackage'])
            ->where('status', 'active');

        // 1. Logika Pencarian (Mencari di tabel Users atau Members)
        if (!empty($this->search)) {
            $query->where(function ($q) {
                $q->whereHas('user', function ($subQ) {
                    $subQ->where('name', 'like', '%' . $this->search . '%');
                })->orWhereHas('members', function ($subQ) {
                    $subQ->where('name', 'like', '%' . $this->search . '%');
                });
            });
        }

        // 2. Logika Filter Waktu
        if ($this->filterTime === 'today') {
            $query->whereDate('created_at', today());
        } elseif ($this->filterTime === 'week') {
            $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
        } elseif ($this->filterTime === 'month') {
            $query->whereMonth('created_at', now()->month)
                  ->whereYear('created_at', now()->year);
        } elseif ($this->filterTime === 'custom' && $this->dateStart && $this->dateEnd) {
            // Logika Flatpickr
            $query->whereBetween('created_at', [
                $this->dateStart . ' 00:00:00', 
                $this->dateEnd . ' 23:59:59'
            ]);
        }

        return $query->latest()->paginate(10);
    }

    public function exportExcel()
    {
        $fileName = 'Data-Membership-' . date('Y-m-d') . '.xlsx';
        
        return Excel::download(
            new MembershipExport(
                $this->search, 
                $this->filterTime, 
                $this->dateStart, 
                $this->dateEnd
            ), 
            $fileName
        );
    }
};
?>

<div>
    <div class="flex sm:flex-row flex-col justify-between items-center mb-6">
    <h5 class="text-xl font-semibold text-heading">Data Membership & Program</h5>
    <div class="flex gap-2">
            {{-- <button wire:click="exportExcel" type="button" class="text-green-700 bg-green-50 box-border border border-green-200 hover:bg-green-100 focus:ring-4 focus:ring-green-200 shadow-xs font-medium leading-5 rounded-md text-sm px-4 py-2.5 focus:outline-none flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                Export Excel
            </button> --}}
            
            {{-- <a href="{{ route('admin.membership.gabung') }}" wire:navigate class="text-white bg-brand box-border border border-transparent hover:bg-brand-strong focus:ring-4 focus:ring-brand-medium shadow-xs font-medium leading-5 rounded-md text-sm px-4 py-2.5 focus:outline-none">+ Pendaftaran Baru</a> --}}
        </div>
    </div>

    {{-- Notifikasi --}}
    @if (session()->has('success'))
        <div class="p-4 mb-4 text-sm text-green-800 rounded-lg bg-green-50" role="alert">
            <span class="font-medium">Sukses!</span> {{ session('success') }}
        </div>
    @endif
    @if (session()->has('error'))
        <div class="p-4 mb-4 text-sm text-red-800 rounded-lg bg-red-50" role="alert">
            <span class="font-medium">Gagal!</span> {{ session('error') }}
        </div>
    @endif

    <div class="relative overflow-x-auto bg-neutral-primary-soft shadow-xs rounded-md border border-default">
        <div class="p-4 flex flex-col md:flex-row items-center justify-between gap-4">
            
            <div class="relative w-full md:w-auto md:flex-1">
                <div class="absolute inset-y-0 start-0 flex items-center ps-3 pointer-events-none">
                    <svg class="w-4 h-4 text-body" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-width="2" d="m21 21-3.5-3.5M17 10a7 7 0 1 1-14 0 7 7 0 0 1 14 0Z"/></svg>
                </div>
                <input type="text" wire:model.live.debounce.300ms="search" class="block w-full max-w-sm ps-9 pe-3 py-2.5 bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base focus:ring-brand focus:border-brand shadow-xs placeholder:text-body" placeholder="Cari nama member...">
            </div>
            
            <div class="flex items-center gap-3 w-full md:w-auto">
                
                <div class="relative w-full md:w-56" wire:ignore>
                    <div class="absolute inset-y-0 start-0 flex items-center ps-3 pointer-events-none">
                        <svg class="w-4 h-4 text-body" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 10h16M8 14h8m-4-7V4M7 7V4m10 3V4M5 20h14a1 1 0 0 0 1-1V7a1 1 0 0 0-1-1H5a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1Z"/></svg>
                    </div>
                    <input type="text" x-data 
                        x-init="flatpickr($el, { 
                            mode: 'range', 
                            dateFormat: 'Y-m-d',
                            placeholder: 'Pilih Tanggal',
                            onClose: function(selectedDates, dateStr, instance) { 
                                @this.call('setDateRange', dateStr) 
                            }
                        })" 
                        class="block w-full ps-9 pe-3 py-2.5 bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base focus:ring-brand focus:border-brand shadow-xs placeholder:text-body" 
                        placeholder="Pilih Rentang Tanggal">
                </div>

                <div x-data="{ open: false }" class="relative w-full md:w-auto">
                    <button @click="open = !open" @click.outside="open = false" class="w-full md:w-auto shrink-0 inline-flex items-center justify-center text-body bg-neutral-secondary-medium box-border border border-default-medium hover:bg-neutral-tertiary-medium hover:text-heading focus:ring-4 focus:ring-neutral-tertiary shadow-xs font-medium leading-5 rounded-base text-sm px-3 py-2.5 focus:outline-none" type="button">
                        <svg class="w-4 h-4 me-1.5 -ms-0.5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-width="2" d="M18.796 4H5.204a1 1 0 0 0-.753 1.659l5.302 6.058a1 1 0 0 1 .247.659v4.874a.5.5 0 0 0 .2.4l3 2.25a.5.5 0 0 0 .8-.4v-7.124a1 1 0 0 1 .247-.659l5.302-6.059c.566-.646.106-1.658-.753-1.658Z"/></svg>
                        
                        @if($filterTime === 'today') Hari Ini
                        @elseif($filterTime === 'week') Minggu Ini
                        @elseif($filterTime === 'month') Bulan Ini
                        @elseif($filterTime === 'custom') Kustom
                        @else Semua Waktu @endif
                        
                        <svg class="w-4 h-4 ms-1.5 -me-0.5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m19 9-7 7-7-7"/></svg>
                    </button>
                    
                    <div x-show="open" x-transition style="display: none;" class="absolute right-0 z-50 mt-2 bg-neutral-primary-medium border border-default-medium rounded-base shadow-lg w-40">
                        <ul class="p-2 text-sm text-body font-medium">
                            <li>
                                <button type="button" wire:click="setFilterTime('all')" @click="open = false" class="inline-flex items-center w-full p-2 hover:bg-neutral-tertiary-medium hover:text-heading rounded {{ $filterTime === 'all' ? 'text-brand font-bold bg-neutral-tertiary-soft' : '' }}">Semua Waktu</button>
                            </li>
                            <li>
                                <button type="button" wire:click="setFilterTime('today')" @click="open = false" class="inline-flex items-center w-full p-2 hover:bg-neutral-tertiary-medium hover:text-heading rounded {{ $filterTime === 'today' ? 'text-brand font-bold bg-neutral-tertiary-soft' : '' }}">Hari ini</button>
                            </li>
                            <li>
                                <button type="button" wire:click="setFilterTime('week')" @click="open = false" class="inline-flex items-center w-full p-2 hover:bg-neutral-tertiary-medium hover:text-heading rounded {{ $filterTime === 'week' ? 'text-brand font-bold bg-neutral-tertiary-soft' : '' }}">Minggu ini</button>
                            </li>
                            <li>
                                <button type="button" wire:click="setFilterTime('month')" @click="open = false" class="inline-flex items-center w-full p-2 hover:bg-neutral-tertiary-medium hover:text-heading rounded {{ $filterTime === 'month' ? 'text-brand font-bold bg-neutral-tertiary-soft' : '' }}">Bulan ini</button>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        <table class="w-full text-sm text-left rtl:text-right text-body">
            <thead class="text-sm text-body bg-neutral-secondary-medium border-b border-default-medium">
                <tr>
                    <th scope="col" class="px-6 py-3 font-medium">No</th>
                    <th scope="col" class="px-6 py-3 font-medium">Member</th>
                    <th scope="col" class="px-6 py-3 font-medium">Program / Paket</th>
                    <th scope="col" class="px-6 py-3 font-medium text-right">Total Bayar</th>
                    <th scope="col" class="px-6 py-3 font-medium">Masa Aktif</th>
                    <th scope="col" class="px-6 py-3 font-medium text-center">Admin Follow Up</th>
                    <th scope="col" class="px-6 py-3 font-medium text-center">Sales Follow Up</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->memberships as $membership)
                    <tr wire:key="{{ $membership->id }}" class="bg-neutral-primary-soft border-b border-default hover:bg-neutral-secondary-medium">
                        
                        {{-- Nomor Urut --}}
                        <td class="px-6 py-4 font-medium text-heading whitespace-nowrap">
                            {{ $loop->iteration + ($this->memberships->currentPage() - 1) * $this->memberships->perPage() }}
                        </td>

                        {{-- INFO MEMBER --}}
                        <td class="px-6 py-4 font-medium text-heading whitespace-nowrap">
                            <div class="flex flex-col gap-1.5">
                                @forelse($membership->members as $member)
                                    <div class="flex items-center gap-2">
                                        <span class="font-semibold">{{ $member->name }}</span>
                                    </div>
                                @empty
                                    <div class="font-semibold">{{ $membership->user->name ?? 'N/A' }}</div>
                                @endforelse
                            </div>
                        </td>

                        {{-- INFO PROGRAM / PAKET & SESI COACH (DIGABUNG) --}}
                        <td class="px-6 py-4 text-heading whitespace-nowrap">
                            <div class="flex flex-col gap-2">
                                
                                {{-- Jika ada Paket Gym (Termasuk Visit) --}}
                                @if(in_array($membership->type, ['membership', 'bundle_pt_membership', 'visit']))
                                    <div>
                                        <div class="text-[10px] text-gray-400 uppercase tracking-wider font-bold mb-0.5">
                                            Paket {{ $membership->type === 'visit' ? 'Harian' : 'Gym' }}
                                        </div>
                                        <div class="font-medium {{ $membership->type === 'visit' ? 'text-orange-600' : 'text-emerald-600' }}">
                                            {{ $membership->gymPackage->name ?? 'Paket Terhapus' }}
                                        </div>
                                    </div>
                                @endif

                                {{-- Jika ada Paket PT --}}
                                @if(in_array($membership->type, ['pt', 'bundle_pt_membership']))
                                    <div class="{{ in_array($membership->type, ['bundle_pt_membership']) ? 'border-t border-gray-200 pt-2' : '' }}">
                                        <div class="text-[10px] text-gray-400 uppercase tracking-wider font-bold mb-0.5">Paket Trainer</div>
                                        <div class="font-medium text-indigo-600">{{ $membership->ptPackage->name ?? 'Paket Terhapus' }}</div>
                                        
                                        <div class="flex items-center gap-3 mt-1">
                                            <div class="text-xs text-gray-500">
                                                Coach: <span class="font-medium text-gray-700">{{ $membership->personalTrainer->name ?? '-' }}</span>
                                            </div>
                                            
                                            {{-- Informasi Sesi Coach Pindahan --}}
                                            @if ($membership->total_sessions)
                                                <div class="text-xs text-gray-500 border-l border-gray-300 pl-3">
                                                    Sisa Sesi: 
                                                    <span class="font-bold {{ $membership->remaining_sessions <= 2 ? 'text-red-600' : 'text-green-600' }}">
                                                        {{ $membership->remaining_sessions }}
                                                    </span> 
                                                    <span class="text-gray-400">/ {{ $membership->total_sessions }}</span>
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </td>

                        {{-- Total Bayar --}}
                        <td class="px-6 py-4 text-right whitespace-nowrap">
    @if($membership->discount_applied > 0)
        @php
            $originalPrice = $membership->price_paid + $membership->discount_applied;
            $percentage = ($originalPrice > 0) ? ($membership->discount_applied / $originalPrice) * 100 : 0;
        @endphp
        
        <div class="flex flex-col items-end mb-1">
            <div class="flex items-center gap-2">
                <span class="text-xs text-gray-400 line-through">Rp {{ number_format($originalPrice, 0, ',', '.') }}</span>
                <span class="bg-green-100 text-green-800 text-[10px] font-bold px-1.5 py-0.5 rounded">
                    -{{ is_float($percentage) ? round($percentage, 1) : $percentage }}%
                </span>
            </div>
            <div class="text-[10px] text-green-600 font-medium mt-0.5">
                Diskon Rp {{ number_format($membership->discount_applied, 0, ',', '.') }}
            </div>
        </div>
    @endif

    <div class="font-bold text-heading text-base">
        Rp {{ number_format($membership->price_paid, 0, ',', '.') }}
    </div>

    {{-- Logika Penentuan Label Harga Sesuai Rentang --}}
    @php
        $priceLabel = null;
        $labelColor = '';
        
        $pricePaid = $membership->price_paid;
        $normalPrice = $membership->normal_price;
        $basePrice = $membership->base_price;
        $netPrice = $membership->net_price;
        $unrecommendedPrice = $membership->unrecommended_price;

        if ($netPrice !== null) {
            // Jika harga lebih besar dari Harga Net (misal: 251.000 > 250.000) -> Normal
            if ($pricePaid > $netPrice) {
                $priceLabel = 'Harga Normal';
                $labelColor = 'bg-blue-100 text-blue-800';
            } else {
                // Harga <= Harga Net (misal: 250.000 ke bawah)
                if ($unrecommendedPrice !== null) {
                    // Jika harga lebih besar dari Harga Tidak Disarankan (misal: 221.000 > 220.000) -> Net
                    if ($pricePaid > $unrecommendedPrice) {
                        $priceLabel = 'Harga Net';
                        $labelColor = 'bg-emerald-100 text-emerald-800';
                    } 
                    // Jika harga <= Harga Tidak Disarankan (misal: 220.000, 119.000) -> Tidak Disarankan
                    else {
                        $priceLabel = 'Harga Tidak Disarankan';
                        $labelColor = 'bg-red-100 text-red-800';
                    }
                } else {
                    // Jika unrecommended_price NULL, semua yang <= net_price masuk ke Harga Net
                    $priceLabel = 'Harga Net';
                    $labelColor = 'bg-emerald-100 text-emerald-800';
                }
            }
        } 
        // Jika net_price NULL tapi unrecommended_price ada
        elseif ($unrecommendedPrice !== null) {
            if ($pricePaid > $unrecommendedPrice) {
                $priceLabel = 'Harga Normal';
                $labelColor = 'bg-blue-100 text-blue-800';
            } else {
                $priceLabel = 'Harga Tidak Disarankan';
                $labelColor = 'bg-red-100 text-red-800';
            }
        } 
        // Jika net_price & unrecommended_price sama-sama NULL (seperti paket Daily Pass)
        else {
            if (($normalPrice !== null && $pricePaid >= $normalPrice) || ($basePrice !== null && $pricePaid >= $basePrice)) {
                $priceLabel = 'Harga Normal';
                $labelColor = 'bg-blue-100 text-blue-800';
            }
        }
    @endphp

    {{-- Hanya cetak div label jika $priceLabel tidak null --}}
    @if($priceLabel)
        <div class="mt-1 flex justify-end">
            <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full {{ $labelColor }}">
                {{ $priceLabel }}
            </span>
        </div>
    @endif
</td>
                        {{-- Masa Aktif --}}
                        <td class="px-6 py-4 whitespace-nowrap text-xs">
                            <div class="flex flex-col gap-1.5">
                                <div class="flex items-center text-gray-600">
                                    <svg class="w-3.5 h-3.5 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                    Mulai: <span class="font-medium text-heading ml-1">{{ $membership->start_date ? $membership->start_date->format('d M Y') : '-' }}</span>
                                </div>

                                {{-- Masa aktif Gym --}}
                                @if(in_array($membership->type, ['membership', 'bundle_pt_membership']))
                                    <div class="flex items-center text-gray-600">
                                        <span class="inline-block w-2 h-2 rounded-full bg-emerald-400 mr-2"></span>
                                        Gym s/d: <span class="font-medium text-emerald-600 ml-1">{{ $membership->membership_end_date ? $membership->membership_end_date->format('d M Y') : '-' }}</span>
                                    </div>
                                @endif

                                {{-- Jika Visit, tampilkan label Kunjungan Harian --}}
                                @if($membership->type === 'visit')
                                    <div class="flex items-center text-gray-600 mt-0.5">
                                        <span class="inline-block w-2 h-2 rounded-full bg-orange-400 mr-2"></span>
                                        <span class="font-medium text-orange-600 ml-1">Berlaku 1 Hari</span>
                                    </div>
                                @endif

                                {{-- Masa aktif PT --}}
                                @if(in_array($membership->type, ['pt', 'bundle_pt_membership']))
                                    <div class="flex items-center text-gray-600">
                                        <span class="inline-block w-2 h-2 rounded-full bg-indigo-400 mr-2"></span>
                                        PT s/d: <span class="font-medium text-indigo-600 ml-1">{{ $membership->pt_end_date ? $membership->pt_end_date->format('d M Y') : '-' }}</span>
                                    </div>
                                @endif
                            </div>
                        </td>

                        <td class="px-6 py-4 font-medium text-heading whitespace-nowrap">
                            <h1 class="font-semibold">{{ $membership->followUp->name ?? '-' }}</h1>
                        </td>
                        <td class="px-6 py-4 font-medium text-heading whitespace-nowrap">
                            <h1 class="font-semibold">{{ $membership->followUpTwo->name ?? '-' }}</h1>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-6 py-8 text-center text-gray-500">
                            Belum ada riwayat transaksi membership.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    
    <div class="mt-4">
        {{ $this->memberships->links() }}
    </div>
</div>