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

    public $showDetailModal = false;
    public $selectedMembershipId = null;

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

    public function openDetailModal($membershipId)
    {
        $this->selectedMembershipId = $membershipId;
        $this->showDetailModal = true;
    }

    public function closeDetailModal()
    {
        $this->showDetailModal = false;
        $this->selectedMembershipId = null;
    }

    public function openCoachModalFromDetail($membershipId)
    {
        $this->closeDetailModal();
        $this->openCoachModal($membershipId);
    }

    #[Computed]
    public function selectedMembership()
    {
        if (! $this->selectedMembershipId) {
            return null;
        }

        return Membership::with(['user', 'members', 'admin', 'followUp', 'followUpTwo', 'personalTrainer', 'gymPackage', 'ptPackage'])
            ->find($this->selectedMembershipId);
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

        return $query->orderByRaw("
            CASE
                WHEN pt_end_date IS NOT NULL AND membership_end_date IS NOT NULL THEN
                    CASE WHEN pt_end_date < membership_end_date THEN pt_end_date ELSE membership_end_date END
                WHEN pt_end_date IS NOT NULL THEN pt_end_date
                WHEN membership_end_date IS NOT NULL THEN membership_end_date
                ELSE start_date
            END ASC
        ")->paginate(10);
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
        <div class="grid grid-cols-1 md:grid-cols-3 xl:grid-cols-3 gap-2">
            @forelse ($this->memberships as $membership)
                <div wire:key="{{ $membership->id }}" wire:click="openDetailModal({{ $membership->id }})" class="bg-neutral-primary-soft rounded-lg border border-default shadow-sm p-2 hover:shadow-md transition-shadow cursor-pointer">

                    {{-- HEADER: Nama Member --}}
                    <div class="flex justify-between items-start ">
                        <div class="flex-1 min-w-0">
                            <h3 class="font-bold text-heading text-base truncate">
                                @forelse($membership->members as $member)
                                    {{ $member->name }}{{ !$loop->last ? ', ' : '' }}
                                @empty
                                    {{ $membership->user->name ?? 'N/A' }}
                                @endforelse
                            </h3>
                        </div>
                    </div>

                    {{-- Progress Bar Masa Aktif --}}
                    @php
                        $now = now();
                        
                        $getProgressData = function($startDate, $endDate) use ($now) {
                            if (!$startDate || !$endDate) return null;
                            
                            $totalDays = $startDate->diffInDays($endDate);
                            $remainingDays = $now->diffInDays($endDate, false);
                            
                            if ($totalDays <= 0) return null;
                            
                            $progress = max(0, min(100, ($remainingDays / $totalDays) * 100));
                            
                            if ($remainingDays <= 0) {
                                $colorClass = 'bg-red-600';
                                $textClass = 'text-red-700';
                            } elseif ($remainingDays <= 7) {
                                $colorClass = 'bg-red-500';
                                $textClass = 'text-red-600';
                            } elseif ($remainingDays <= 30) {
                                $colorClass = 'bg-yellow-500';
                                $textClass = 'text-yellow-600';
                            } else {
                                $colorClass = 'bg-green-500';
                                $textClass = 'text-green-600';
                            }
                            
                            $remainingMonths = floor($remainingDays / 30);
                            $remainingDaysAfterMonths = $remainingDays % 30;
                            
                            return [
                                'progress' => $progress,
                                'remainingDays' => $remainingDays,
                                'remainingMonths' => $remainingMonths,
                                'remainingDaysAfterMonths' => $remainingDaysAfterMonths,
                                'colorClass' => $colorClass,
                                'textClass' => $textClass,
                                'endDateFormatted' => $endDate->format('d M Y'),
                            ];
                        };
                    @endphp

                    <div class="space-y-2">
                        {{-- Progress Bar Gym --}}
                        @if(in_array($membership->type, ['membership', 'bundle_pt_membership']))
                            @php $gymData = $getProgressData($membership->start_date, $membership->membership_end_date); @endphp
                            @if($gymData)
                                <div class="space-y-1">
                                    <div class="flex justify-between items-center text-xs">
                                        <span class="text-xs font-medium {{ $membership->type === 'visit' ? 'text-orange-700' : 'text-emerald-700' }} truncate max-w-[50%]">{{ $membership->gymPackage->name ?? 'Paket Terhapus' }}</span>
                                        @if($gymData['remainingDays'] > 0)
                                            <span class="font-medium {{ $gymData['textClass'] }}">
                                                Sisa {{ $gymData['remainingMonths'] }} bulan {{ $gymData['remainingDaysAfterMonths'] }} hari
                                            </span>
                                        @else
                                            <span class="font-medium text-red-600">Expired</span>
                                        @endif
                                    </div>
                                    <div class="w-full bg-gray-200 rounded-full h-2">
                                        <div class="{{ $gymData['colorClass'] }} h-2 rounded-full transition-all duration-300" style="width: {{ $gymData['progress'] }}%"></div>
                                    </div>
                                    <div class="text-right text-[10px] text-gray-400">
                                        Berlaku sampai {{ $gymData['endDateFormatted'] }}
                                    </div>
                                </div>
                            @endif
                        @endif

                        {{-- Progress Bar Visit --}}
                        @if($membership->type === 'visit')
                            <div class="space-y-1">
                                <div class="flex justify-between items-center text-xs">
                                    <span class="text-gray-500">Kunjungan</span>
                                    <span class="font-medium text-orange-600">Berlaku 1 Hari</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div class="bg-orange-500 h-2 rounded-full transition-all duration-300" style="width: 100%"></div>
                                </div>
                                <div class="text-right text-[10px] text-gray-400">
                                    @if($membership->start_date)
                                        Berlaku sampai {{ $membership->start_date->format('d M Y') }}
                                    @else
                                        Tanggal tidak tersedia
                                    @endif
                                </div>
                            </div>
                        @endif

                        {{-- Progress Bar PT --}}
                        @if(in_array($membership->type, ['pt', 'bundle_pt_membership']))
                            @php $ptData = $getProgressData($membership->start_date, $membership->pt_end_date); @endphp
                            @if($ptData)
                                <div class="space-y-1">
                                    <div class="flex justify-between items-center text-xs">
                                        <span class="text-xs font-medium text-indigo-700 truncate max-w-[50%]">{{ $membership->ptPackage->name ?? 'Paket Terhapus' }}</span>
                                        @if($ptData['remainingDays'] > 0)
                                            <span class="font-medium {{ $ptData['textClass'] }}">
                                                Sisa {{ $ptData['remainingMonths'] }} bulan {{ $ptData['remainingDaysAfterMonths'] }} hari
                                            </span>
                                        @else
                                            <span class="font-medium text-red-600">Expired</span>
                                        @endif
                                    </div>
                                    <div class="w-full bg-gray-200 rounded-full h-2">
                                        <div class="{{ $ptData['colorClass'] }} h-2 rounded-full transition-all duration-300" style="width: {{ $ptData['progress'] }}%"></div>
                                    </div>
                                    <div class="text-right text-[10px] text-gray-400">
                                        Berlaku sampai {{ $ptData['endDateFormatted'] }}
                                    </div>
                                </div>
                            @endif
                        @endif
                    </div>
                </div>
            @empty
                <div class="col-span-full py-8 text-center text-gray-500 bg-neutral-primary-soft rounded-lg border border-default">
                    Belum ada riwayat transaksi membership.
                </div>
            @endforelse
        </div>
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

    {{-- Detail Modal --}}
    @if ($showDetailModal && $this->selectedMembership)
        <div class="fixed inset-0 z-50 flex items-center justify-center backdrop-blur-sm">
            <div class="bg-white rounded-lg shadow-xl max-w-lg w-full mx-4 max-h-[90vh] overflow-y-auto">
                <div class="p-6 border-b border-default-medium flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-heading">Detail Membership</h3>
                    <button type="button" wire:click="closeDetailModal()" class="text-body hover:text-heading">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>

                <div class="p-6 space-y-4">
                    {{-- Info Member --}}
                    <div class="bg-neutral-secondary-medium p-4 rounded-md">
                        <p class="text-xs text-gray-500 uppercase font-bold mb-1">Member</p>
                        <p class="font-semibold text-heading">
                            @php
                                $memberNames = collect([$this->selectedMembership->user->name ?? 'N/A']);
                                foreach($this->selectedMembership->members as $member) {
                                    if($member->name !== $this->selectedMembership->user->name) {
                                        $memberNames->push($member->name);
                                    }
                                }
                            @endphp
                            {{ $memberNames->join(', ') }}
                        </p>
                    </div>

                    {{-- Info Paket --}}
                    <div class="grid grid-cols-2 gap-3">
                        @if(in_array($this->selectedMembership->type, ['membership', 'bundle_pt_membership', 'visit']))
                            <div class="bg-neutral-secondary-medium p-3 rounded-md">
                                <p class="text-xs text-gray-500 uppercase font-bold mb-1">Paket Gym</p>
                                <p class="font-medium text-heading text-sm">{{ $this->selectedMembership->gymPackage->name ?? '-' }}</p>
                                @if($this->selectedMembership->membership_end_date)
                                    <p class="text-xs text-gray-400 mt-1">Sampai {{ $this->selectedMembership->membership_end_date->format('d M Y') }}</p>
                                @endif
                            </div>
                        @endif

                        @if(in_array($this->selectedMembership->type, ['pt', 'bundle_pt_membership']))
                            <div class="bg-neutral-secondary-medium p-3 rounded-md">
                                <p class="text-xs text-gray-500 uppercase font-bold mb-1">Paket PT</p>
                                <p class="font-medium text-heading text-sm">{{ $this->selectedMembership->ptPackage->name ?? '-' }}</p>
                                @if($this->selectedMembership->pt_end_date)
                                    <p class="text-xs text-gray-400 mt-1">Sampai {{ $this->selectedMembership->pt_end_date->format('d M Y') }}</p>
                                @endif
                                
                                @if($this->selectedMembership->personalTrainer)
                                    <p class="text-xs text-gray-400 mt-1">Coach: {{ $this->selectedMembership->personalTrainer->name }}</p>
                                @else
                                    <p class="text-xs text-gray-400 mt-1 italic">Coach: Belum ada</p>
                                @endif
                                
                                @if($this->selectedMembership->total_sessions)
                                    <p class="text-xs text-gray-400 mt-1">
                                        Sisa Sesi: {{ $this->selectedMembership->remaining_sessions }}/{{ $this->selectedMembership->total_sessions }}
                                    </p>
                                @endif
                            </div>
                        @endif
                    </div>

                    {{-- Info Kunjungan --}}
                    @if($this->selectedMembership->type === 'visit')
                        <div class="bg-neutral-secondary-medium p-3 rounded-md">
                            <p class="text-xs text-gray-500 uppercase font-bold mb-1">Jenis</p>
                            <p class="font-medium text-heading">Kunjungan (1 Hari)</p>
                            @if($this->selectedMembership->start_date)
                                <p class="text-xs text-gray-400 mt-1">Tanggal: {{ $this->selectedMembership->start_date->format('d M Y') }}</p>
                            @endif
                        </div>
                    @endif

                </div>

                {{-- Footer Buttons --}}
                @if(auth()->check() && auth()->user()->role !== 'head_coach')
                    <div class="p-6 border-t border-default-medium flex flex-wrap gap-3 justify-end">
                        <button type="button" wire:click="closeDetailModal()"
                            class="px-4 py-2 text-heading bg-neutral-secondary-medium border border-default-medium rounded-md hover:bg-neutral-secondary-strong font-medium text-sm">
                            Tutup
                        </button>

                        @if($this->selectedMembership->ptPackage && !$this->selectedMembership->pt_id)
                            <button type="button" wire:click="openCoachModalFromDetail({{ $this->selectedMembership->id }})" 
                                class="px-4 py-2 text-indigo-700 bg-indigo-50 border border-indigo-200 rounded-md hover:bg-indigo-100 font-medium text-sm">
                                Pilih Coach
                            </button>
                        @endif

                        <a href="{{ route('admin.membership.renew', ['id' => $this->selectedMembership->id]) }}" wire:navigate 
                            class="px-4 py-2 text-blue-700 bg-blue-50 border border-blue-200 rounded-md hover:bg-blue-100 font-medium text-sm inline-flex items-center gap-1">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"></path><path d="M21 3v5h-5"></path><path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"></path><path d="M3 21v-5h5"></path></svg>
                            Perpanjang
                        </a>

                        @if(auth()->check() && auth()->user()->role === 'admin')
                            <button type="button" wire:click="delete({{ $this->selectedMembership->id }})" wire:confirm="Apakah Anda yakin ingin menghapus membership ini?"
                                class="px-4 py-2 text-red-700 bg-red-50 border border-red-200 rounded-md hover:bg-red-100 font-medium text-sm inline-flex items-center gap-1">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"></path><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path><line x1="10" x2="10" y1="11" y2="17"></line><line x1="14" x2="14" y1="11" y2="17"></line></svg>
                                Hapus
                            </button>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>