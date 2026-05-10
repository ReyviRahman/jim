<?php

namespace App\Livewire\Admin;

use App\Models\Membership;
use App\Models\User;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::admin')] class extends Component
{
    use WithPagination;

    public $search = '';

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

        return Membership::with(['user', 'members', 'admin', 'followUp', 'followUpTwo', 'personalTrainer', 'ptPackage'])
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

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function delete($membershipId)
    {
        if (auth()->check() && auth()->user()->role !== 'admin') {
            session()->flash('error', 'Akses ditolak! Hanya Admin yang dapat menghapus data ini.');
            return;
        }

        $membership = Membership::findOrFail($membershipId);
        $membership->delete();

        session()->flash('success', 'Membership PT berhasil dihapus.');
    }

    #[Computed]
    public function memberships()
    {
        $query = Membership::with(['user', 'members', 'admin', 'followUp', 'followUpTwo', 'personalTrainer', 'ptPackage'])
            ->whereNotNull('pt_package_id')
            ->where('is_active', true)
            ->where('status', 'active');

        if (! empty($this->search)) {
            $query->where(function ($q) {
                $q->whereHas('user', function ($subQ) {
                    $subQ->where('name', 'like', '%'.$this->search.'%');
                })->orWhereHas('members', function ($subQ) {
                    $subQ->where('name', 'like', '%'.$this->search.'%');
                });
            });
        }

        return $query->orderByRaw('COALESCE(remaining_sessions, 999) ASC')->paginate(12);
    }
};
?>

<div>
    <div class="flex sm:flex-row flex-col justify-between items-center mb-6">
    <h5 class="text-xl font-semibold text-heading">Data PT Berjalan</h5>
    <div class="flex gap-2">
        </div>
    </div>

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
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 xl:grid-cols-3 gap-2">
            @forelse ($this->memberships as $membership)
                <div wire:key="{{ $membership->id }}" wire:click="openDetailModal({{ $membership->id }})" class="bg-neutral-primary-soft rounded-lg border border-default shadow-sm p-2 hover:shadow-md transition-shadow cursor-pointer">

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

                    @php
                        $totalSessions = $membership->total_sessions ?? 0;
                        $remainingSessions = $membership->remaining_sessions ?? 0;
                        $usedSessions = $totalSessions - $remainingSessions;
                        $progressPercent = $totalSessions > 0 ? ($remainingSessions / $totalSessions) * 100 : 0;
                        
                        if ($remainingSessions <= 0) {
                            $colorClass = 'bg-red-600';
                            $textClass = 'text-red-700';
                        } elseif ($remainingSessions <= 2) {
                            $colorClass = 'bg-red-500';
                            $textClass = 'text-red-600';
                        } elseif ($remainingSessions <= 5) {
                            $colorClass = 'bg-yellow-500';
                            $textClass = 'text-yellow-600';
                        } else {
                            $colorClass = 'bg-green-500';
                            $textClass = 'text-green-600';
                        }
                    @endphp

                    <div class="space-y-2 mt-2">
                        <div class="space-y-1">
                            <div class="flex justify-between items-center text-xs">
                                <span class="text-xs font-medium text-indigo-700 truncate max-w-[50%]">{{ $membership->ptPackage->name ?? 'Paket Terhapus' }}</span>
                                @if($remainingSessions > 0)
                                    <span class="font-medium {{ $textClass }}">
                                        Sisa {{ $remainingSessions }} sesi
                                    </span>
                                @else
                                    <span class="font-medium text-red-600">Sesi Habis</span>
                                @endif
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="{{ $colorClass }} h-2 rounded-full transition-all duration-300" style="width: {{ $progressPercent }}%"></div>
                            </div>
                            <div class="flex justify-between text-[10px] text-gray-400">
                                <span>Total: {{ $totalSessions }} sesi</span>
                                <span>Terpakai: {{ $usedSessions }} sesi</span>
                            </div>
                        </div>
                        
                        @if($membership->personalTrainer)
                            <div class="text-xs text-gray-500 mt-1">
                                <span class="font-medium">Coach:</span> {{ $membership->personalTrainer->name }}
                            </div>
                        @else
                            <div class="text-xs text-orange-600 mt-1 italic">
                                Coach: Belum ditentukan
                            </div>
                        @endif
                    </div>
                </div>
            @empty
                <div class="col-span-full py-8 text-center text-gray-500 bg-neutral-primary-soft rounded-lg border border-default">
                    Belum ada data PT yang sedang berjalan.
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

    @if ($showDetailModal && $this->selectedMembership)
        <div class="fixed inset-0 z-50 flex items-center justify-center backdrop-blur-sm">
            <div class="bg-white rounded-lg shadow-xl max-w-lg w-full mx-4 max-h-[90vh] overflow-y-auto">
                <div class="p-6 border-b border-default-medium flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-heading">Detail PT Berjalan</h3>
                    <button type="button" wire:click="closeDetailModal()" class="text-body hover:text-heading">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>

                <div class="p-6 space-y-4">
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

                    <div class="bg-neutral-secondary-medium p-3 rounded-md">
                        <p class="text-xs text-gray-500 uppercase font-bold mb-1">Paket PT</p>
                        <p class="font-medium text-heading text-sm">{{ $this->selectedMembership->ptPackage->name ?? '-' }}</p>
                        @if($this->selectedMembership->total_sessions)
                            <p class="text-xs text-gray-400 mt-1">
                                Total Sesi: {{ $this->selectedMembership->total_sessions }}
                            </p>
                            <p class="text-xs text-gray-400 mt-1">
                                Sisa Sesi: {{ $this->selectedMembership->remaining_sessions }}
                            </p>
                        @endif
                        
                        @if($this->selectedMembership->personalTrainer)
                            <p class="text-xs text-gray-400 mt-1">Coach: {{ $this->selectedMembership->personalTrainer->name }}</p>
                        @else
                            <p class="text-xs text-gray-400 mt-1 italic">Coach: Belum ada</p>
                        @endif
                    </div>

                    @if($this->selectedMembership->start_date)
                        <div class="bg-neutral-secondary-medium p-3 rounded-md">
                            <p class="text-xs text-gray-500 uppercase font-bold mb-1">Tanggal Mulai</p>
                            <p class="font-medium text-heading">{{ $this->selectedMembership->start_date->format('d M Y') }}</p>
                        </div>
                    @endif
                </div>

                @if(auth()->check() && auth()->user()->role !== 'head_coach')
                    <div class="p-6 border-t border-default-medium flex flex-wrap gap-3 justify-end">
                        <button type="button" wire:click="closeDetailModal()"
                            class="px-4 py-2 text-heading bg-neutral-secondary-medium border border-default-medium rounded-md hover:bg-neutral-secondary-strong font-medium text-sm">
                            Tutup
                        </button>

                        @if(!$this->selectedMembership->pt_id)
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
                            <button type="button" wire:click="delete({{ $this->selectedMembership->id }})" wire:confirm="Apakah Anda yakin ingin menghapus data PT ini?"
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
