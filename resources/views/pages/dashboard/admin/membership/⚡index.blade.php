<?php

namespace App\Livewire\Admin;

use App\Exports\MembershipExport;
use App\Models\Membership;
use App\Models\User;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;

new #[Layout('layouts::admin')] class extends Component
{
    use WithPagination;

    // --- VARIABEL UNTUK FILTER & PENCARIAN ---
    public $search = '';

    public $filterTime = 'all'; // all, today, week, month, custom

    public $dateStart = null;

    public $dateEnd = null;

    public $showCoachModal = false;
    public $selectedMembershipForCoach = null;
    public $selectedCoachId = null;

    #[Computed]
    public function trainers()
    {
        return User::where('role', 'pt')->where('is_active', true)->get();
    }

    public function openCoachModal($membershipId)
    {
        $this->selectedMembershipForCoach = $membershipId;
        $this->selectedCoachId = null;
        $membership = Membership::find($membershipId);
        if ($membership && $membership->pt_id) {
            $this->selectedCoachId = $membership->pt_id;
        }
        $this->showCoachModal = true;
    }

    public function closeCoachModal()
    {
        $this->showCoachModal = false;
        $this->selectedMembershipForCoach = null;
        $this->selectedCoachId = null;
    }

    public function saveCoach()
    {
        if (!$this->selectedCoachId) {
            $this->addError('coach', 'Pilih coach terlebih dahulu.');
            return;
        }

        $membership = Membership::find($this->selectedMembershipForCoach);
        if ($membership) {
            $membership->update(['pt_id' => $this->selectedCoachId]);
            session()->flash('success', 'Coach berhasil dipilih!');
        }
        $this->closeCoachModal();
    }

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
                'status' => 'active',
            ]);

            session()->flash('success', 'Membership berhasil diaktifkan!');
        } else {
            session()->flash('error', 'Gagal: Membership ini tidak dalam status pending.');
        }
    }

    public function reject($membershipId)
    {
        $membership = Membership::findOrFail($membershipId);

        if ($membership->status === 'pending') {
            $membership->update([
                'status' => 'rejected',
            ]);

            session()->flash('success', 'Pengajuan membership berhasil ditolak.');
        } else {
            session()->flash('error', 'Gagal: Membership ini tidak dalam status pending.');
        }
    }

    public function delete($membershipId)
    {
        // 1. Cek apakah user login dan apakah role-nya BUKAN admin
        if (auth()->check() && auth()->user()->role !== 'admin') {
            session()->flash('error', 'Akses ditolak! Hanya Admin yang dapat menghapus data ini.');
            return; // Hentikan proses eksekusi di sini
        }

        // 2. Jika lolos pengecekan, lanjutkan proses hapus
        $membership = Membership::findOrFail($membershipId);
        $membership->delete();

        session()->flash('success', 'Membership dan semua data terkait berhasil dihapus.');
    }

    #[Computed]
    public function memberships()
    {
        $query = Membership::with(['user', 'members', 'admin', 'followUp', 'followUpTwo', 'personalTrainer', 'gymPackage', 'ptPackage'])
            ->where('is_active', true)
            ->where('status', 'active');

        // 1. Logika Pencarian (Mencari di tabel Users atau Members)
        if (! empty($this->search)) {
            $query->where(function ($q) {
                $q->whereHas('user', function ($subQ) {
                    $subQ->where('name', 'like', '%'.$this->search.'%');
                })->orWhereHas('members', function ($subQ) {
                    $subQ->where('name', 'like', '%'.$this->search.'%');
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
                $this->dateStart.' 00:00:00',
                $this->dateEnd.' 23:59:59',
            ]);
        }

        return $query->latest()->paginate(10);
    }

    public function exportExcel()
    {
        $fileName = 'Data-Membership-'.date('Y-m-d').'.xlsx';

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
                    <th scope="col" class="px-6 py-3 font-medium text-center">Aksi</th>
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
                            @if(auth()->check() && auth()->user()->role === 'admin')
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
                        <td class="px-6 py-4 text-center whitespace-nowrap">
                            <div class="flex items-center justify-center gap-2">
                                @if($membership->ptPackage && !$membership->pt_id)
                                    <button type="button" wire:click="openCoachModal({{ $membership->id }})" 
                                        class="inline-flex items-center gap-1 px-3 py-2 text-sm font-medium text-indigo-700 bg-indigo-50 border border-indigo-200 rounded-md hover:bg-indigo-100 focus:ring-2 focus:ring-indigo-300 transition-colors">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 512 512"><path fill="currentColor" d="M211.832 39.06c-15.022 15.31-15.894 22.83-23.473 43.903c2.69 9.14 5.154 16.927 9.148 25.117c5.158.283 10.765.47 15.342.43c-6.11-10.208-8.276-19.32-4.733-35.274c4.3 19.05 12.847 29.993 21.203 34.332q4.548-.5 8.776-1.146c-6.255-10.337-8.494-19.47-4.914-35.588c3.897 17.27 11.287 27.876 18.86 32.94c4.658-1.043 9.283-2.243 13.927-3.534c-5.517-9.69-7.36-18.692-3.97-33.957c3.357 14.876 9.307 24.81 15.732 30.516a1528 1528 0 0 0 13.852-4.347c-.685-5.782-.416-12.187 1.064-19.115l1.883-8.8l17.603 3.76l-1.88 8.804c-3.636 17.008 1.324 24.42 7.306 28.666c5.98 4.244 14.69 3.46 16.03 2.6l7.576-4.86l9.72 15.15c-3.857 2.34-7.9 5.44-11.822 7.06c18.65 27.678 32.183 61.465 24.756 93.55c-2.365 9.474-6.03 18.243-11.715 24.986c12.725 12.13 21.215 22.026 31.032 34.5a692 692 0 0 0-11.692-7.37c-11.397-7.01-23.832-14.214-34.98-19.802c-16.012-7.8-31.367-18.205-47.73-20.523c-22.552-2.967-46.27 4.797-73.32 21.06c7.872 8.72 13.282 15.474 20.312 24.288c-6.98-4.338-14.652-9.07-23.16-14.23c-32.554-17.48-65.39-48.227-100.438-49.99c-30.56-1.092-59.952 14.955-89.677 38.568L18 254.293V494h31.963c45.184-17.437 80.287-57.654 97.03-94.52l.25-.564l.325-.52c9.463-15.252 11.148-29.688 16.79-44.732c5.645-15.044 16.907-29.718 41.884-38.756c4.353-2.16 5.07-1.415 8.633 1.395c30.468 24.01 57.29 32.02 83.24 32.35c32.61-1.557 58.442-9.882 85.682-19.38c-3.966 3.528-8.77 7.21-13.986 10.762c-15.323 10.436-34.217 19.928-46.304 24.8c-14.716 2.006-28.36 2.416-41.967.616c-9.96 12.09-25.574 20.358-37.35 26.673c63.92 14.023 115.88.91 167.386-22.896c-9.522-1.817-19.008-3.692-27.994-5.42c31.634-4.422 64.984-3.766 94.705-3.53c4.084-.02 7.213-.453 8.7-.886c14.167-51.072-4.095-97.893-34.294-145.216c-30.263-47.425-72.18-94.107-101.896-143.04c-21.1-17.257-48.6-31.455-77.522-46.175c-20.386 4.25-41.026 9.336-61.443 14.1zm85.385 70.49c-11.678 3.6-23.71 7.425-33.852 10.012c2.527 4.93 3.735 10.664 3.395 16.202c11.028.877 21.082-2.018 28.965-6.356c4.845-2.666 8.74-6.048 11.414-8.96c-3.854-2.735-7.26-6.41-9.923-10.9zm-54.213 14.698c-11.76 1.143-24.59 2.362-35.06 2.236c2.39 4.772 3.78 12.067 8.51 14.84c11.18 1.164 20.6 1.997 29.91-1.746c5.435-3.214 1.818-15.058-3.36-15.33m-34.98 209.332c-17.593 7.233-22.586 15.14-26.813 26.406c-3.998 10.66-6.227 25.076-14.48 41.014c32.29-6.38 69.625-21.23 93.852-40.088c-17.017-5.098-34.553-13.852-52.557-27.332zm9.318 71.385c-18.723 7.237-40.836 16.144-59.696 14.062C143.774 446.68 124.012 474.03 91.762 494h84.68c21.564-29.798 38.067-56.575 40.9-89.035"/></svg>
                                        Pilih Coach
                                    </button>
                                @endif
                                <a href="{{ route('admin.membership.renew', ['id' => $membership->id]) }}" wire:navigate class="inline-flex items-center gap-1 px-3 py-2 text-sm font-medium text-blue-700 bg-blue-50 border border-blue-200 rounded-md hover:bg-blue-100 focus:ring-2 focus:ring-blue-300 transition-colors">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"></path><path d="M21 3v5h-5"></path><path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"></path><path d="M3 21v-5h5"></path></svg>
                                    Perpanjang
                                </a>
                                @if(auth()->check() && auth()->user()->role === 'admin')
                                    {{-- <a href="{{ route('admin.membership.edit', ['id' => $membership->id]) }}" wire:navigate class="inline-flex items-center gap-1 px-3 py-2 text-sm font-medium text-yellow-700 bg-yellow-50 border border-yellow-200 rounded-md hover:bg-yellow-100 focus:ring-2 focus:ring-yellow-300 transition-colors">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"></path></svg>
                                        Edit
                                    </a> --}}
                                    <button type="button" wire:click="delete({{ $membership->id }})" wire:confirm="Apakah Anda yakin ingin menghapus membership ini?" class="inline-flex items-center gap-1 px-3 py-2 text-sm font-medium text-red-700 bg-red-50 border border-red-200 rounded-md hover:bg-red-100 focus:ring-2 focus:ring-red-300 transition-colors">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"></path><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path><line x1="10" x2="10" y1="11" y2="17"></line><line x1="14" x2="14" y1="11" y2="17"></line></svg>
                                        Hapus
                                    </button>
                                @endif
                            </div>
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

    @if ($showCoachModal && $selectedMembershipForCoach)
        @php
            $coachMembership = \App\Models\Membership::find($selectedMembershipForCoach);
        @endphp
    <div class="fixed inset-0 z-50 flex items-center justify-center backdrop-blur-sm">
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4">
            <div class="p-6 border-b border-default-medium flex items-center justify-between">
                <h3 class="text-lg font-semibold text-heading">Pilih Coach</h3>
                <button type="button" wire:click="closeCoachModal()" class="text-body hover:text-heading">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>

            <form wire:submit.prevent="saveCoach">
                <div class="p-6 space-y-4">
                    @if ($errors->has('coach'))
                        <div class="p-3 bg-red-100 border border-red-400 text-red-700 rounded text-sm">
                            {{ $errors->first('coach') }}
                        </div>
                    @endif

                    @if($coachMembership)
                        <div class="bg-neutral-secondary-medium p-3 rounded-md">
                            <div>
                                <p class="text-xs text-gray-500 uppercase font-bold">Member</p>
                                <p class="font-semibold text-heading">{{ $coachMembership->user->name }}</p>
                            </div>
                            <div class="mt-2">
                                <p class="text-xs text-gray-500 uppercase font-bold">Paket Trainer</p>
                                <p class="font-semibold text-heading text-indigo-600">{{ $coachMembership->ptPackage->name }}</p>
                            </div>
                        </div>
                    @endif

                    <div>
                        <label for="selectedCoachId" class="block text-sm font-medium text-heading mb-1">
                            Pilih Coach
                        </label>
                        <select id="selectedCoachId" wire:model.live="selectedCoachId"
                            class="w-full px-3 py-2 bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base focus:ring-brand focus:border-brand shadow-xs">
                            <option value="">-- Pilih Coach --</option>
                            @foreach($this->trainers as $trainer)
                                <option value="{{ $trainer->id }}">{{ $trainer->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="p-6 border-t border-default-medium flex gap-3 justify-end">
                    <button type="button" wire:click="closeCoachModal()"
                        class="px-4 py-2 text-heading bg-neutral-secondary-medium border border-default-medium rounded-md hover:bg-neutral-secondary-strong font-medium text-sm">
                        Batal
                    </button>
                    <button type="submit"
                        class="px-4 py-2 text-white bg-indigo-600 hover:bg-indigo-700 rounded-md font-medium text-sm">
                        Simpan Coach
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endif
</div>