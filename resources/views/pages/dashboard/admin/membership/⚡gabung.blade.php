<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed; 
use Livewire\WithPagination;
use App\Models\Membership;
use Carbon\Carbon;

new #[Layout('layouts::admin')] class extends Component
{
    use WithPagination;

    public $search = '';
    public $selectedMembershipId = null;
    public $startDate = '';
    public $endDate = '';
    public $showModal = false;

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function openModal($membershipId)
    {
        $this->selectedMembershipId = $membershipId;
        $this->startDate = '';
        $this->endDate = '';
        $this->showModal = true;
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->selectedMembershipId = null;
    }

    // Fungsi untuk menampilkan format tanggal + hari bahasa Indonesia
    public function getFormattedDate($date)
    {
        if (!$date) return '';
        Carbon::setLocale('id');
        return Carbon::parse($date)->translatedFormat('l, d F Y');
    }

    public function aktivatekan()
    {
        if (!$this->startDate || !$this->endDate) {
            $this->addError('dates', 'Tanggal mulai dan selesai harus diisi.');
            return;
        }

        if (strtotime($this->startDate) > strtotime($this->endDate)) {
            $this->addError('dates', 'Tanggal mulai tidak boleh lebih besar dari tanggal selesai.');
            return;
        }

        $membership = Membership::find($this->selectedMembershipId);
        
        if (!$membership) {
            $this->addError('membership', 'Membership tidak ditemukan.');
            return;
        }

        $updateData = [
            'is_active' => true,
            'start_date' => $this->startDate,
        ];

        // Jika ada gym_package_id, isi membership_end_date
        if ($membership->gym_package_id) {
            $updateData['membership_end_date'] = $this->endDate;
        }

        // Jika ada pt_package_id, isi pt_end_date
        if ($membership->pt_package_id) {
            $updateData['pt_end_date'] = $this->endDate;
        }

        $membership->update($updateData);

        session()->flash('success', 'Member berhasil diaktifkan!');
        $this->closeModal();
        $this->resetPage();
    }

    #[Computed]
    public function memberships()
    {
        return Membership::where('status', 'active')
            ->where('is_active', false)
            ->with('user', 'gymPackage', 'ptPackage', 'personalTrainer', 'followUp', 'followUpTwo')
            ->where(function ($query) {
                $query->whereHas('user', function ($q) {
                    $q->where('name', 'like', '%' . $this->search . '%')
                      ->orWhere('email', 'like', '%' . $this->search . '%');
                })
                ->orWhere('notes', 'like', '%' . $this->search . '%');
            })
            ->latest()
            ->paginate(10);
    }
};
?>

<div>
    @if (session()->has('error'))
        <div class="mb-4 p-3 bg-red-100 border border-red-400 text-red-700 rounded text-sm">
            {{ session('error') }}
        </div>
    @endif
    @if (session()->has('success'))
        <div class="mb-4 p-3 bg-green-100 border border-green-400 text-green-700 rounded text-sm">
            {{ session('success') }}
        </div>
    @endif

    <div class="relative overflow-x-auto bg-neutral-primary-soft shadow-xs rounded-base border border-default">
        <div class="flex items-center flex-column flex-wrap md:flex-row space-y-4 md:space-y-0 p-4">
            <div>
                <h5 class="text-xl font-semibold text-heading">Aktivasi Member</h5>
                <p class="text-sm text-body mt-1">Pilih member untuk diaktifkan akun keanggotaannya.</p>
            </div>
            
            <div class="relative ms-auto">
                <div class="absolute inset-y-0 start-0 flex items-center ps-3 pointer-events-none">
                    <svg class="w-4 h-4 text-body" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                        <path stroke="currentColor" stroke-linecap="round" stroke-width="2" d="m21 21-3.5-3.5M17 10a7 7 0 1 1-14 0 7 7 0 0 1 14 0Z"/>
                    </svg>
                </div>
                <input type="text" id="table-search" wire:model.live="search" 
                    class="block w-full max-w-96 ps-9 pe-3 py-2 bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base focus:ring-brand focus:border-brand shadow-xs placeholder:text-body" 
                    placeholder="Cari nama atau email...">
            </div>
        </div>

        <table class="w-full text-sm text-left rtl:text-right text-body">
            <thead class="text-sm text-body bg-neutral-secondary-medium border-b border-default-medium">
                <tr>
                    <th scope="col" class="px-6 py-3 font-medium">No</th>
                    <th scope="col" class="px-6 py-3 font-medium">Member</th>
                    <th scope="col" class="px-6 py-3 font-medium">Program / Paket</th>
                    <th scope="col" class="px-6 py-3 font-medium text-right">Total Bayar</th>
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
                            <div class="flex items-center gap-2">
                                @if($membership->user->photo)
                                    <img class="w-8 h-8 rounded-full object-cover" src="{{ asset('storage/' . $membership->user->photo) }}" alt="{{ $membership->user->name }}">
                                @else
                                    <img class="w-8 h-8 rounded-full object-cover" src="https://ui-avatars.com/api/?name={{ urlencode($membership->user->name) }}&background=random" alt="{{ $membership->user->name }}">
                                @endif
                                <div class="flex flex-col">
                                    <span class="font-semibold">{{ $membership->user->name }}</span>
                                    <span class="text-xs text-gray-500">{{ $membership->user->email }}</span>
                                </div>
                            </div>
                        </td>

                        {{-- INFO PROGRAM / PAKET (DIGABUNG) --}}
                        <td class="px-6 py-4 text-heading whitespace-nowrap">
                            <div class="flex flex-col gap-2">
                                
                                {{-- Jika ada Paket Gym --}}
                                @if($membership->gymPackage)
                                    <div>
                                        <div class="text-[10px] text-gray-400 uppercase tracking-wider font-bold mb-0.5">
                                            Paket Gym
                                        </div>
                                        <div class="font-medium text-emerald-600">
                                            {{ $membership->gymPackage->name }}
                                        </div>
                                    </div>
                                @endif

                                {{-- Jika ada Paket PT --}}
                                @if($membership->ptPackage)
                                    <div class="{{ $membership->gymPackage ? 'border-t border-gray-200 pt-2' : '' }}">
                                        <div class="text-[10px] text-gray-400 uppercase tracking-wider font-bold mb-0.5">Paket Trainer</div>
                                        <div class="font-medium text-indigo-600">{{ $membership->ptPackage->name }}</div>
                                        
                                        <div class="flex items-center gap-3 mt-1">
                                            <div class="text-xs text-gray-500">
                                                Coach: <span class="font-medium text-gray-700">{{ $membership->personalTrainer->name ?? '-' }}</span>
                                            </div>
                                            
                                            {{-- Informasi Sesi Coach --}}
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
                            <div class="font-bold text-heading text-base">
                                Rp {{ number_format($membership->price_paid ?? 0, 0, ',', '.') }}
                            </div>
                        </td>

                        {{-- Admin Follow Up --}}
                        <td class="px-6 py-4 font-medium text-heading whitespace-nowrap">
                            <h1 class="font-semibold">{{ $membership->followUp->name ?? '-' }}</h1>
                        </td>

                        {{-- Sales Follow Up --}}
                        <td class="px-6 py-4 font-medium text-heading whitespace-nowrap">
                            <h1 class="font-semibold">{{ $membership->followUpTwo->name ?? '-' }}</h1>
                        </td>

                        {{-- AKSI --}}
                        <td class="px-6 py-4 text-center whitespace-nowrap">
                            <button type="button" wire:click="openModal({{ $membership->id }})" 
                                class="inline-flex items-center gap-1 px-3 py-2 text-sm font-medium text-white bg-brand hover:bg-brand-strong rounded-md focus:ring-2 focus:ring-brand-medium transition-colors">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
                                Aktivasi
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-6 py-8 text-center text-body">
                            Tidak ada membership dengan status aktif yang belum diaktifkan.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        
        <div class="p-4 border-t border-default-medium">
            {{ $this->memberships->links('components.custom-pagination') }}
        </div>
    </div>

    @if ($showModal && $selectedMembershipId)
        @php
            $modalMembership = \App\Models\Membership::find($selectedMembershipId);
            $durationText = '';
            
            if ($startDate && $endDate) {
                // LOGIKA CARBON DIFF YANG SUDAH DIPERBAIKI
                $start = \Carbon\Carbon::parse($startDate)->startOfDay();
                $end = \Carbon\Carbon::parse($endDate)->startOfDay();
                $diff = $start->diff($end);
                
                $months = ($diff->y * 12) + $diff->m;
                $days = $diff->d;
                
                $parts = [];
                if ($months > 0) $parts[] = $months . ' Bulan';
                if ($days > 0) $parts[] = $days . ' Hari';
                
                if (empty($parts)) {
                    $durationText = 'Hari yang sama';
                } else {
                    $durationText = implode(' ', $parts);
                }
            }
        @endphp
    <div class="fixed inset-0 z-50 flex items-center justify-center backdrop-blur-sm">
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4">
            <div class="p-6 border-b border-default-medium flex items-center justify-between">
                <h3 class="text-lg font-semibold text-heading">Aktivasi Member</h3>
                <button type="button" wire:click="closeModal()" class="text-body hover:text-heading">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>

            <form wire:submit.prevent="aktivatekan">
                <div class="p-6 space-y-4 max-h-[70vh] overflow-y-auto">
                    @if ($errors->has('dates'))
                        <div class="p-3 bg-red-100 border border-red-400 text-red-700 rounded text-sm">
                            {{ $errors->first('dates') }}
                        </div>
                    @endif
                    @if ($errors->has('membership'))
                        <div class="p-3 bg-red-100 border border-red-400 text-red-700 rounded text-sm">
                            {{ $errors->first('membership') }}
                        </div>
                    @endif

                    {{-- Info Member --}}
                    @if($modalMembership)
                        <div class="bg-neutral-secondary-medium p-3 rounded-md space-y-2">
                            <div>
                                <p class="text-xs text-gray-500 uppercase font-bold">Member</p>
                                <p class="font-semibold text-heading">{{ $modalMembership->user->name }}</p>
                            </div>

                            {{-- Nama Paket --}}
                            <div>
                                <p class="text-xs text-gray-500 uppercase font-bold">Paket</p>
                                <p class="font-semibold text-heading">
                                    @if($modalMembership->gymPackage && $modalMembership->ptPackage)
                                        {{ $modalMembership->gymPackage->name }} + {{ $modalMembership->ptPackage->name }}
                                    @elseif($modalMembership->gymPackage)
                                        {{ $modalMembership->gymPackage->name }}
                                    @elseif($modalMembership->ptPackage)
                                        {{ $modalMembership->ptPackage->name }}
                                    @else
                                        -
                                    @endif
                                </p>
                            </div>

                            {{-- Coach (jika ada PT) --}}
                            @if($modalMembership->ptPackage && $modalMembership->personalTrainer)
                                <div>
                                    <p class="text-xs text-gray-500 uppercase font-bold">Coach</p>
                                    <p class="font-semibold text-heading">{{ $modalMembership->personalTrainer->name }}</p>
                                </div>
                            @endif

                            {{-- Follow Up --}}
                            @if($modalMembership->followUp)
                                <div>
                                    <p class="text-xs text-gray-500 uppercase font-bold">Admin Follow Up</p>
                                    <p class="font-semibold text-heading">{{ $modalMembership->followUp->name }}</p>
                                </div>
                            @endif

                            {{-- Sales Follow Up --}}
                            @if($modalMembership->followUpTwo)
                                <div>
                                    <p class="text-xs text-gray-500 uppercase font-bold">Sales Follow Up</p>
                                    <p class="font-semibold text-heading">{{ $modalMembership->followUpTwo->name }}</p>
                                </div>
                            @endif
                        </div>
                    @endif

                    <div>
                        <label for="startDate" class="block text-sm font-medium text-heading mb-1">
                            Tanggal Mulai
                        </label>
                        <input type="date" id="startDate" wire:model.live="startDate"
                            class="w-full px-3 py-2 bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base focus:ring-brand focus:border-brand shadow-xs">
                        {{-- Menampilkan format teks tanggal dan hari (cth: Jumat, 01 Mei 2026) --}}
                        <p class="mt-1.5 text-xs text-brand-strong font-medium">{{ $this->getFormattedDate($startDate) }}</p>
                    </div>

                    <div>
                        <label for="endDate" class="block text-sm font-medium text-heading mb-1">
                            Tanggal Selesai
                        </label>
                        <input type="date" id="endDate" wire:model.live="endDate"
                            class="w-full px-3 py-2 bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base focus:ring-brand focus:border-brand shadow-xs">
                        {{-- Menampilkan format teks tanggal dan hari (cth: Jumat, 01 Mei 2026) --}}
                        <p class="mt-1.5 text-xs text-brand-strong font-medium">{{ $this->getFormattedDate($endDate) }}</p>
                    </div>

                    {{-- Duration Display --}}
                    @if($durationText)
                        <div class="bg-blue-50 border border-blue-200 p-3 rounded-md">
                            <p class="text-xs text-blue-600 uppercase font-bold mb-1">Total Durasi Program</p>
                            <p class="text-lg font-bold text-blue-700">{{ $durationText }}</p>
                        </div>
                    @endif
                </div>

                <div class="p-6 border-t border-default-medium flex gap-3 justify-end">
                    <button type="button" wire:click="closeModal()"
                        class="px-4 py-2 text-heading bg-neutral-secondary-medium border border-default-medium rounded-md hover:bg-neutral-secondary-strong font-medium text-sm">
                        Batal
                    </button>
                    <button type="submit"
                        class="px-4 py-2 text-white bg-brand hover:bg-brand-strong rounded-md font-medium text-sm">
                        Aktivasi
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endif
</div>