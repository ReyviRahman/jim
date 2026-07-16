<?php

namespace App\Livewire\Admin; 

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed; 
use Livewire\WithPagination;
use App\Models\User;
use Illuminate\Support\Facades\DB;

new #[Layout('layouts::admin')] class extends Component
{
    use WithPagination;

    public $search = '';
    public $filterTime = 'all';
    public $dateStart = null;
    public $dateEnd = null;

    public $sortBy = 'latest_membership_date';
    public $sortDirection = 'desc';

    public function sort($column)
    {
        $allowed = ['latest_membership_date'];

        if (! in_array($column, $allowed)) {
            return;
        }

        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = 'desc';
        }

        $this->resetPage();
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingSortBy()
    {
        $this->resetPage();
    }

    public function updatingSortDirection()
    {
        $this->resetPage();
    }

    public function setFilterTime($time)
    {
        $this->filterTime = $time;
        $this->dateStart = null;
        $this->dateEnd = null;
        $this->resetPage();
    }

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

    private function applyDateFilter($query)
    {
        if ($this->filterTime === 'today') {
            $query->whereDate('created_at', today());
        } elseif ($this->filterTime === 'week') {
            $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
        } elseif ($this->filterTime === 'month') {
            $query->whereMonth('created_at', now()->month)
                  ->whereYear('created_at', now()->year);
        } elseif ($this->filterTime === 'custom' && $this->dateStart && $this->dateEnd) {
            $query->whereBetween('created_at', [
                $this->dateStart . ' 00:00:00',
                $this->dateEnd . ' 23:59:59'
            ]);
        }
    }

    public function goToDetail($userId)
    {
        return $this->redirectRoute('admin.riwayat.detail', $userId, navigate: true);
    }

    #[Computed]
    public function users()
    {
        return User::where('role', 'member')
            ->where(function ($q) {
                $q->whereHas('paidMemberships')
                  ->orWhereHas('memberships');
            })
            ->when($this->search, function ($q) {
                $q->where(function ($sq) {
                    $sq->where('name', 'like', '%' . $this->search . '%')
                       ->orWhere('email', 'like', '%' . $this->search . '%');
                });
            })
            ->when($this->filterTime !== 'all', function ($q) {
                $q->where(function ($sq) {
                    $sq->whereHas('paidMemberships', fn ($mq) => $this->applyDateFilter($mq))
                       ->orWhereHas('memberships', fn ($mq) => $this->applyDateFilter($mq));
                });
            })
            ->when($this->sortBy === 'latest_membership_date', function ($q) {
                $q->orderBy(DB::raw('(
                    SELECT MAX(memberships.created_at)
                    FROM memberships
                    WHERE memberships.user_id = users.id
                       OR memberships.id IN (
                           SELECT membership_users.membership_id
                           FROM membership_users
                           WHERE membership_users.user_id = users.id
                       )
                )'), $this->sortDirection);
            })
            ->paginate(10);
    }
};
?>

<div>
    <div class="flex sm:flex-row flex-col justify-between items-center mb-6">
        <h5 class="text-xl font-semibold text-heading">Data Riwayat Membership</h5>
        <div class="flex gap-2"></div>
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
                    <th scope="col" class="px-6 py-3 font-medium text-center">Invoice</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->users as $user)
                    @php $latest = $user->latestMembership; @endphp
                    <tr wire:key="{{ $user->id }}" wire:click="goToDetail({{ $user->id }})" class="bg-neutral-primary-soft border-b border-default hover:bg-neutral-secondary-medium cursor-pointer">
                        
                        {{-- Nomor Urut --}}
                        <td class="px-6 py-4 font-medium text-heading whitespace-nowrap">
                            {{ $loop->iteration + ($this->users->currentPage() - 1) * $this->users->perPage() }}
                        </td>

                        {{-- INFO MEMBER --}}
                        <td class="px-6 py-4 font-medium text-heading whitespace-nowrap">
                            <div class="flex items-center gap-2">
                                @if($user->photo)
                                    <img class="w-8 h-8 rounded-full object-cover" src="{{ asset('storage/' . $user->photo) }}" alt="{{ $user->name }}">
                                @else
                                    <img class="w-8 h-8 rounded-full object-cover" src="https://ui-avatars.com/api/?name={{ urlencode($user->name) }}&background=random" alt="{{ $user->name }}">
                                @endif
                                <div class="flex flex-col">
                                    <span class="font-semibold">{{ $user->name }}</span>
                                    <span class="text-xs text-gray-500">{{ $user->email }}</span>
                                </div>
                            </div>
                        </td>

                        {{-- INFO PROGRAM / PAKET --}}
                        <td class="px-6 py-4 text-heading whitespace-nowrap">
                            @if($latest)
                                <div class="flex flex-col gap-2">
                                    @if(in_array($latest->type, ['membership', 'bundle_pt_membership', 'visit']))
                                        <div>
                                            <div class="text-[10px] text-gray-400 uppercase tracking-wider font-bold mb-0.5">
                                                Paket {{ $latest->type === 'visit' ? 'Harian' : 'Gym' }}
                                            </div>
                                            <div class="font-medium {{ $latest->type === 'visit' ? 'text-orange-600' : 'text-emerald-600' }}">
                                                {{ $latest->gymPackage->name ?? 'Paket Terhapus' }}
                                            </div>
                                        </div>
                                    @endif

                                    @if(in_array($latest->type, ['pt', 'bundle_pt_membership']))
                                        <div class="{{ in_array($latest->type, ['bundle_pt_membership']) ? 'border-t border-gray-200 pt-2' : '' }}">
                                            <div class="text-[10px] text-gray-400 uppercase tracking-wider font-bold mb-0.5">Paket Trainer</div>
                                            <div class="font-medium text-indigo-600">{{ $latest->ptPackage->name ?? 'Paket Terhapus' }}</div>
                                            
                                            <div class="flex items-center gap-3 mt-1">
                                                <div class="text-xs text-gray-500">
                                                    Coach: <span class="font-medium text-gray-700">{{ $latest->personalTrainer->name ?? '-' }}</span>
                                                </div>
                                                
                                                @if ($latest->total_sessions)
                                                    <div class="text-xs text-gray-500 border-l border-gray-300 pl-3">
                                                        Sisa Sesi: 
                                                        <span class="font-bold {{ $latest->remaining_sessions <= 2 ? 'text-red-600' : 'text-green-600' }}">
                                                            {{ $latest->remaining_sessions }}
                                                        </span> 
                                                        <span class="text-gray-400">/ {{ $latest->total_sessions }}</span>
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            @else
                                <span class="text-gray-400">-</span>
                            @endif
                        </td>

                        {{-- Total Bayar --}}
                        <td class="px-6 py-4 text-right whitespace-nowrap">
                            @if($latest)
                                @if($latest->discount_applied > 0)
                                    @php
                                        $originalPrice = $latest->price_paid + $latest->discount_applied;
                                        $percentage = ($originalPrice > 0) ? ($latest->discount_applied / $originalPrice) * 100 : 0;
                                    @endphp
                                    
                                    <div class="flex flex-col items-end mb-1">
                                        <div class="flex items-center gap-2">
                                            <span class="text-xs text-gray-400 line-through">Rp {{ number_format($originalPrice, 0, ',', '.') }}</span>
                                            <span class="bg-green-100 text-green-800 text-[10px] font-bold px-1.5 py-0.5 rounded">
                                                -{{ is_float($percentage) ? round($percentage, 1) : $percentage }}%
                                            </span>
                                        </div>
                                        <div class="text-[10px] text-green-600 font-medium mt-0.5">
                                            Diskon Rp {{ number_format($latest->discount_applied, 0, ',', '.') }}
                                        </div>
                                    </div>
                                @endif

                                <div class="font-bold text-heading text-base">
                                    Rp {{ number_format($latest->price_paid, 0, ',', '.') }}
                                </div>

                                @if(auth()->check() && auth()->user()->role === 'admin')
                                    @php
                                        $priceLabelData = $latest->getPriceLabel();
                                    @endphp

                                    @if($priceLabelData)
                                        <div class="mt-1 flex justify-end">
                                            <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full {{ $priceLabelData['color'] }}">
                                                {{ $priceLabelData['label'] }}
                                            </span>
                                        </div>
                                    @endif
                                @endif
                            @else
                                <span class="text-gray-400">-</span>
                            @endif
                        </td>

                        {{-- Masa Aktif --}}
                        <td class="px-6 py-4 whitespace-nowrap text-xs">
                            @if($latest)
                                <div class="flex flex-col gap-1.5">
                                    <div class="flex items-center text-gray-600">
                                        <svg class="w-3.5 h-3.5 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                        Mulai: <span class="font-medium text-heading ml-1">{{ $latest->start_date ? $latest->start_date->format('d M Y') : 'BELUM AKTIF' }}</span>
                                    </div>

                                    @if(in_array($latest->type, ['membership', 'bundle_pt_membership']))
                                        <div class="flex items-center text-gray-600">
                                            <span class="inline-block w-2 h-2 rounded-full bg-emerald-400 mr-2"></span>
                                            Gym s/d: <span class="font-medium text-emerald-600 ml-1">{{ $latest->membership_end_date ? $latest->membership_end_date->format('d M Y') : 'BELUM AKTIF' }}</span>
                                        </div>
                                    @endif

                                    @if($latest->type === 'visit')
                                        <div class="flex items-center text-gray-600 mt-0.5">
                                            <span class="inline-block w-2 h-2 rounded-full bg-orange-400 mr-2"></span>
                                            <span class="font-medium text-orange-600 ml-1">Berlaku 1 Hari</span>
                                        </div>
                                    @endif

                                    @if(in_array($latest->type, ['pt', 'bundle_pt_membership']))
                                        <div class="flex items-center text-gray-600">
                                            <span class="inline-block w-2 h-2 rounded-full bg-indigo-400 mr-2"></span>
                                            PT s/d: <span class="font-medium text-indigo-600 ml-1">{{ $latest->pt_end_date ? $latest->pt_end_date->format('d M Y') : 'BELUM AKTIF' }}</span>
                                        </div>
                                    @endif
                                </div>
                            @else
                                <span class="text-gray-400">-</span>
                            @endif
                        </td>

                        <td class="px-6 py-4 font-medium text-heading whitespace-nowrap text-center">
                            <span class="font-semibold">{{ $latest->followUp->name ?? '-' }}</span>
                        </td>
                        <td class="px-6 py-4 font-medium text-heading whitespace-nowrap text-center">
                            <span class="font-semibold">{{ $latest->followUpTwo->name ?? '-' }}</span>
                        </td>
                        <td class="px-6 py-4 text-center whitespace-nowrap">
                            @if($latest)
                                <span x-data>
                                    <a href="{{ route('admin.riwayat.membership.invoice', $latest) }}" @click.stop
                                        class="inline-flex items-center justify-center rounded-md p-2 text-fg-brand hover:bg-brand-soft hover:text-brand-strong"
                                        title="Unduh Invoice">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5V6.75a3.375 3.375 0 00-3.375-3.375h-1.5A3.375 3.375 0 006.375 6.75v1.5h-1.5A3.375 3.375 0 001.5 11.625v6.75a2.625 2.625 0 002.625 2.625h15.75a2.625 2.625 0 002.625-2.625v-4.125a2.625 2.625 0 00-2.625-2.625z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 15.75h9m-9-3h9" />
                                        </svg>
                                    </a>
                                </span>
                            @else
                                <span class="text-gray-400">-</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-6 py-8 text-center text-gray-500">
                            Belum ada riwayat transaksi membership.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    
    <div class="mt-4">
        {{ $this->users->links() }}
    </div>
</div>
