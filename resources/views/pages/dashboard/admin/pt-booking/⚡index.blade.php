<?php

namespace App\Livewire\Admin;

use App\Models\Membership;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;

new #[Layout('layouts::admin')] class extends Component
{
    use WithPagination;

    public string $search = '';

    #[Locked]
    public ?int $selectedMembershipId = null;

    public string $startDate = '';
    public string $endDate = '';
    public bool $showModal = false;
    public $selectedCoachId = null;

    public function boot(): void
    {
        abort_unless(auth()->check() && (in_array(auth()->user()->role, ['admin', 'kasir_gym'], true) || auth()->user()->isHeadCoach()), 403);
    }

    private function membershipQuery(): Builder
    {
        return Membership::query()->where('status', 'active')
            ->where('type', 'pt')->whereNull('pt_id')->whereNull('pt_end_date');
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function openModal(int $membershipId): void
    {
        abort_unless($this->membershipQuery()->whereKey($membershipId)->exists(), 404);
        $this->closeModal();
        $this->selectedMembershipId = $membershipId;
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->reset('selectedMembershipId', 'startDate', 'endDate', 'showModal', 'selectedCoachId');
        $this->resetValidation();
        unset($this->selectedMembership);
    }

    #[Computed]
    public function selectedMembership(): ?Membership
    {
        return $this->selectedMembershipId === null ? null : $this->membershipQuery()
            ->with(['user', 'gymPackage', 'ptPackage'])->find($this->selectedMembershipId);
    }

    #[Computed]
    public function trainers(): Collection
    {
        return User::where('role', 'pt')->where('is_active', true)->get();
    }

    public function getFormattedDate(?string $date): string
    {
        return $date ? Carbon::parse($date)->locale('id')->translatedFormat('l, d F Y') : '';
    }

    public function aktivatekan(): void
    {
        $validated = $this->validate([
            'startDate' => ['required', 'date_format:Y-m-d'],
            'endDate' => ['required', 'date_format:Y-m-d', 'after_or_equal:startDate'],
            'selectedCoachId' => ['required', 'integer', Rule::exists(User::class, 'id')->where('role', 'pt')->where('is_active', true)],
        ], [
            'startDate.required' => 'Tanggal mulai harus diisi.',
            'startDate.date_format' => 'Tanggal mulai tidak valid.',
            'endDate.required' => 'Tanggal selesai harus diisi.',
            'endDate.date_format' => 'Tanggal selesai tidak valid.',
            'endDate.after_or_equal' => 'Tanggal selesai tidak boleh sebelum tanggal mulai.',
            'selectedCoachId.required' => 'Pilih coach terlebih dahulu.',
            'selectedCoachId.exists' => 'Coach harus merupakan PT yang aktif.',
        ]);

        DB::transaction(function () use ($validated): void {
            $membership = $this->membershipQuery()->lockForUpdate()->find($this->selectedMembershipId);
            if (! $membership) {
                throw ValidationException::withMessages(['membership' => 'Paket sudah berubah atau tidak lagi tersedia untuk aktivasi. Muat ulang daftar.']);
            }

            $coach = User::where('role', 'pt')->where('is_active', true)
                ->lockForUpdate()->find($validated['selectedCoachId']);
            if (! $coach) {
                throw ValidationException::withMessages(['selectedCoachId' => 'Coach harus merupakan PT yang aktif.']);
            }

            $membership->update([
                'is_active' => true,
                'start_date' => $validated['startDate'],
                'pt_id' => $coach->id,
                'pt_end_date' => $validated['endDate'],
            ]);
        });

        session()->flash('success', 'PT berhasil diaktifkan!');
        $this->closeModal();
        $this->resetPage();
        unset($this->memberships);
    }

    #[Computed]
    public function memberships(): LengthAwarePaginator
    {
        return $this->membershipQuery()
            ->with(['user', 'gymPackage', 'ptPackage', 'personalTrainer', 'followUp', 'followUpTwo'])
            ->where(function (Builder $query): void {
                $query->whereHas('user', function (Builder $user): void {
                    $user->where(function (Builder $contact): void {
                        $contact->where('name', 'like', '%'.$this->search.'%')
                            ->orWhere('email', 'like', '%'.$this->search.'%');
                    });
                })->orWhere('notes', 'like', '%'.$this->search.'%');
            })
            ->latest()->orderByDesc('id')->paginate(10);
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

    <div class="relative overflow-hidden bg-neutral-primary-soft shadow-xs rounded-base border border-default">
        <div class="flex items-center flex-column flex-wrap md:flex-row space-y-4 md:space-y-0 p-4">
            <div>
                <h5 class="text-xl font-semibold text-heading">PT Onboarding</h5>
                <p class="text-sm text-body mt-1">Paket PT yang belum memiliki coach dan tanggal akhir PT.</p>
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

        <table data-responsive-table data-responsive-breakpoint="xl" class="table-fixed w-full text-sm text-left rtl:text-right text-body">
            <thead class="text-sm text-body bg-neutral-secondary-medium border-b border-default-medium">
                <tr>
                    <th scope="col" class="px-6 py-3 font-medium">No</th>
                    <th scope="col" class="px-6 py-3 font-medium">Member</th>
                    <th scope="col" class="px-6 py-3 font-medium">Program / Paket</th>
                    <th scope="col" class="px-6 py-3 font-medium">Masa Aktif</th>
                    <th scope="col" class="px-6 py-3 font-medium text-right">Total Bayar</th>
                    <th scope="col" class="px-6 py-3 font-medium text-center">Admin Follow Up</th>
                    <th scope="col" class="px-6 py-3 font-medium text-center">Sales Follow Up</th>
                    <th scope="col" class="px-6 py-3 font-medium text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->memberships as $membership)
                    <tr wire:key="{{ $membership->id }}" class="bg-neutral-primary-soft border-b border-default hover:bg-neutral-secondary-medium">
                        
                        <td class="px-6 py-4 font-medium text-heading">
                            {{ $loop->iteration + ($this->memberships->currentPage() - 1) * $this->memberships->perPage() }}
                        </td>

                        <td class="px-6 py-4 font-medium text-heading">
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

                        <td class="px-6 py-4 text-heading">
                            <div class="flex flex-col gap-2">
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

                                @if($membership->ptPackage)
                                    <div class="{{ $membership->gymPackage ? 'border-t border-gray-200 pt-2' : '' }}">
                                        <div class="text-[10px] text-gray-400 uppercase tracking-wider font-bold mb-0.5">Paket Trainer</div>
                                        <div class="font-medium text-indigo-600">{{ $membership->ptPackage->name }}</div>
                                        
                                        <div class="flex items-center gap-3 mt-1">
                                            <div class="text-xs text-gray-500">
                                                Coach: <span class="font-medium text-gray-700">{{ $membership->personalTrainer->name ?? '-' }}</span>
                                            </div>
                                            
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

                        <td class="px-6 py-4 text-heading">
                            @if($membership->start_date && $membership->pt_end_date)
                                <div class="flex flex-col">
                                    <span class="text-xs text-gray-500">{{ $this->getFormattedDate($membership->start_date) }}</span>
                                    <span class="text-xs text-gray-400">s/d</span>
                                    <span class="text-xs text-gray-500">{{ $this->getFormattedDate($membership->pt_end_date) }}</span>
                                </div>
                            @else
                                <span class="text-gray-400">-</span>
                            @endif
                        </td>

                        <td class="px-6 py-4 text-right">
                            <div class="font-bold text-heading text-base">
                                Rp {{ number_format($membership->price_paid ?? 0, 0, ',', '.') }}
                            </div>
                        </td>

                        <td class="px-6 py-4 font-medium text-heading">
                            <h1 class="font-semibold">{{ $membership->followUp->name ?? '-' }}</h1>
                        </td>

                        <td class="px-6 py-4 font-medium text-heading">
                            <h1 class="font-semibold">{{ $membership->followUpTwo->name ?? '-' }}</h1>
                        </td>

                        <td class="px-6 py-4 text-center">
                            <button type="button" wire:click="openModal({{ $membership->id }})"
                                class="inline-flex items-center gap-1 px-3 py-2 text-sm font-medium text-white bg-brand hover:bg-brand-strong rounded-md focus:ring-2 focus:ring-brand-medium transition-colors">
                                Aktivasi PT
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-6 py-8 text-center text-body">
                            Tidak ada paket PT yang belum memiliki coach dan tanggal akhir PT.
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
        <div class="fixed inset-0 z-50 flex items-center justify-center backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="activation-title">
            <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4">
                <div class="p-6 border-b border-default-medium">
                    <h3 id="activation-title" class="text-lg font-semibold text-heading">Aktivasi PT</h3>
                </div>
                <form wire:submit="aktivatekan">
                    <div class="p-6 space-y-4 max-h-[70vh] overflow-y-auto">
                        @error('membership')
                            <p class="text-sm text-red-600" role="alert">{{ $message }}</p>
                        @enderror
                        @if ($this->selectedMembership)
                            <div class="bg-neutral-secondary-medium p-3 rounded-md">
                                <p class="font-semibold text-heading">{{ $this->selectedMembership->user?->name ?? '-' }}</p>
                                <p class="text-sm text-body">{{ $this->selectedMembership->package_name ?? $this->selectedMembership->ptPackage?->name ?? '-' }}</p>
                            </div>
                        @endif
                        <div>
                            <label for="selectedCoachId" class="block text-sm font-medium text-heading mb-1">Coach</label>
                            <select id="selectedCoachId" wire:model="selectedCoachId" required
                                class="w-full px-3 py-2 bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base focus:ring-brand focus:border-brand shadow-xs">
                                <option value="">-- Pilih Coach --</option>
                                @foreach ($this->trainers as $trainer)
                                    <option value="{{ $trainer->id }}">{{ $trainer->name }}</option>
                                @endforeach
                            </select>
                            @error('selectedCoachId') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="startDate" class="block text-sm font-medium text-heading mb-1">Tanggal Mulai</label>
                            <input type="date" id="startDate" wire:model="startDate" required
                                class="w-full px-3 py-2 bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base focus:ring-brand focus:border-brand shadow-xs">
                            @error('startDate') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="endDate" class="block text-sm font-medium text-heading mb-1">Tanggal Akhir PT</label>
                            <input type="date" id="endDate" wire:model="endDate" required
                                class="w-full px-3 py-2 bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base focus:ring-brand focus:border-brand shadow-xs">
                            @error('endDate') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <div class="p-6 border-t border-default-medium flex gap-3 justify-end">
                        <button type="button" wire:click="closeModal"
                            class="px-4 py-2 text-heading bg-neutral-secondary-medium border border-default-medium rounded-md hover:bg-neutral-secondary-strong font-medium text-sm">Batal</button>
                        <button type="submit" wire:loading.attr="disabled" wire:target="aktivatekan"
                            class="px-4 py-2 text-white bg-brand hover:bg-brand-strong rounded-md font-medium text-sm">Aktivasi PT</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
