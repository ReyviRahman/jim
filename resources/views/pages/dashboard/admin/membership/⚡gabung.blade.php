<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed; 
use Livewire\WithPagination;
use App\Models\Membership;
use App\Models\User;
use Carbon\Carbon;

new #[Layout('layouts::admin')] class extends Component
{
    use WithPagination;

    public $search = '';
    public $selectedMembershipId = null;
    public $startDate = '';
    public $endDate = '';
    public $showModal = false;
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
                            @if($membership->ptPackage && !$membership->pt_id)
                                <button type="button" wire:click="openCoachModal({{ $membership->id }})" 
                                    class="inline-flex items-center gap-1 px-3 py-2 text-sm font-medium text-indigo-700 bg-indigo-50 border border-indigo-200 rounded-md hover:bg-indigo-100 focus:ring-2 focus:ring-indigo-300 transition-colors mb-1">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 512 512"><path fill="currentColor" d="M211.832 39.06c-15.022 15.31-15.894 22.83-23.473 43.903c2.69 9.14 5.154 16.927 9.148 25.117c5.158.283 10.765.47 15.342.43c-6.11-10.208-8.276-19.32-4.733-35.274c4.3 19.05 12.847 29.993 21.203 34.332q4.548-.5 8.776-1.146c-6.255-10.337-8.494-19.47-4.914-35.588c3.897 17.27 11.287 27.876 18.86 32.94c4.658-1.043 9.283-2.243 13.927-3.534c-5.517-9.69-7.36-18.692-3.97-33.957c3.357 14.876 9.307 24.81 15.732 30.516a1528 1528 0 0 0 13.852-4.347c-.685-5.782-.416-12.187 1.064-19.115l1.883-8.8l17.603 3.76l-1.88 8.804c-3.636 17.008 1.324 24.42 7.306 28.666c5.98 4.244 14.69 3.46 16.03 2.6l7.576-4.86l9.72 15.15c-3.857 2.34-7.9 5.44-11.822 7.06c18.65 27.678 32.183 61.465 24.756 93.55c-2.365 9.474-6.03 18.243-11.715 24.986c12.725 12.13 21.215 22.026 31.032 34.5a692 692 0 0 0-11.692-7.37c-11.397-7.01-23.832-14.214-34.98-19.802c-16.012-7.8-31.367-18.205-47.73-20.523c-22.552-2.967-46.27 4.797-73.32 21.06c7.872 8.72 13.282 15.474 20.312 24.288c-6.98-4.338-14.652-9.07-23.16-14.23c-32.554-17.48-65.39-48.227-100.438-49.99c-30.56-1.092-59.952 14.955-89.677 38.568L18 254.293V494h31.963c45.184-17.437 80.287-57.654 97.03-94.52l.25-.564l.325-.52c9.463-15.252 11.148-29.688 16.79-44.732c5.645-15.044 16.907-29.718 41.884-38.756c4.353-2.16 5.07-1.415 8.633 1.395c30.468 24.01 57.29 32.02 83.24 32.35c32.61-1.557 58.442-9.882 85.682-19.38c-3.966 3.528-8.77 7.21-13.986 10.762c-15.323 10.436-34.217 19.928-46.304 24.8c-14.716 2.006-28.36 2.416-41.967.616c-9.96 12.09-25.574 20.358-37.35 26.673c63.92 14.023 115.88.91 167.386-22.896c-9.522-1.817-19.008-3.692-27.994-5.42c31.634-4.422 64.984-3.766 94.705-3.53c4.084-.02 7.213-.453 8.7-.886c14.167-51.072-4.095-97.893-34.294-145.216c-30.263-47.425-72.18-94.107-101.896-143.04c-21.1-17.257-48.6-31.455-77.522-46.175c-20.386 4.25-41.026 9.336-61.443 14.1zm85.385 70.49c-11.678 3.6-23.71 7.425-33.852 10.012c2.527 4.93 3.735 10.664 3.395 16.202c11.028.877 21.082-2.018 28.965-6.356c4.845-2.666 8.74-6.048 11.414-8.96c-3.854-2.735-7.26-6.41-9.923-10.9zm-54.213 14.698c-11.76 1.143-24.59 2.362-35.06 2.236c2.39 4.772 3.78 12.067 8.51 14.84c11.18 1.164 20.6 1.997 29.91-1.746c5.435-3.214 1.818-15.058-3.36-15.33m-34.98 209.332c-17.593 7.233-22.586 15.14-26.813 26.406c-3.998 10.66-6.227 25.076-14.48 41.014c32.29-6.38 69.625-21.23 93.852-40.088c-17.017-5.098-34.553-13.852-52.557-27.332zm9.318 71.385c-18.723 7.237-40.836 16.144-59.696 14.062C143.774 446.68 124.012 474.03 91.762 494h84.68c21.564-29.798 38.067-56.575 40.9-89.035"/></svg>
                                    Pilih Coach
                                </button>
                            @endif
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