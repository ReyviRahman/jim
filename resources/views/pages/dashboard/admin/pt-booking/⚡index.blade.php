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
            ->where('type', 'pt')
            ->where(function (Builder $query): void {
                $query->whereNull('pt_id')->orWhereNull('pt_end_date');
            });
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

<div class="pt-booking-page">
    <img src="{{ asset('member-attendance-gym-v2.png') }}" alt="" class="pt-booking-background" aria-hidden="true">
    <div class="pt-booking-content">
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

    <header class="pt-booking-header">
        <p class="pt-booking-eyebrow">Personal Training</p>
        <h1>PT <span>Booking</span></h1>
        <p class="pt-booking-description">Paket PT yang belum memiliki coach<br class="hidden sm:block"> atau tanggal akhir PT.</p>
    </header>

    <div class="pt-booking-search">
        <label for="table-search" class="sr-only">Cari nama atau email</label>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><circle cx="10.5" cy="10.5" r="7.5" /><path d="m16 16 5 5" /></svg>
        <input type="search" id="table-search" wire:model.live.debounce.300ms="search" placeholder="Cari nama atau email..." autocomplete="off">
    </div>

    @if ($this->memberships->isEmpty())
        <section class="pt-booking-empty" aria-labelledby="pt-booking-empty-title" role="status">
            <svg class="pt-booking-empty-icon" viewBox="0 0 104 100" fill="none" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M56 87H17a8 8 0 0 1-8-8V24a8 8 0 0 1 8-8h56a8 8 0 0 1 8 8v16M24 7v20M65 7v20M9 34h72" stroke="#b9bdc1" stroke-width="6" />
                <path d="M28 51h.1M46 51h.1M28 68h.1" stroke="#b9bdc1" stroke-width="6" />
                <circle cx="80" cy="72" r="19" stroke="#fff000" stroke-width="5" />
                <path d="M80 61v12l7 5" stroke="#fff000" stroke-width="4" />
            </svg>
            <h2 id="pt-booking-empty-title">{{ $search !== '' ? 'Paket PT tidak ditemukan' : 'Tidak ada paket PT' }}</h2>
            <p class="pt-booking-empty-description">{{ $search !== '' ? 'Tidak ada paket PT yang cocok dengan pencarian. Coba nama atau email lain.' : 'yang belum memiliki coach atau tanggal akhir PT.' }}</p>
            @if ($search !== '')
                <button type="button" wire:click="$set('search', '')" class="pt-booking-cta">Hapus pencarian</button>
            @else
                <a href="{{ route('admin.akun.member.index') }}" wire:navigate class="pt-booking-cta">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M12 4v16M4 12h16" /></svg>
                    Booking PT Sekarang
                </a>
                <p class="pt-booking-empty-caption">Mulai perjalanan fitness kamu bersama<br class="hidden sm:block"> coach profesional di FRANSGYM.</p>
            @endif
        </section>
    @else
    <div class="pt-booking-data">

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
                                <span class="relative grid size-9 shrink-0 place-items-center overflow-hidden rounded-full bg-white/10 text-brand" aria-hidden="true">
                                    {{ \Illuminate\Support\Str::of($membership->user->name)->substr(0, 1)->upper() }}
                                    @if ($membership->user->photo)
                                        <img src="{{ asset('storage/'.$membership->user->photo) }}" alt="" class="absolute inset-0 size-full object-cover" x-data="{ failed: false }" x-show="!failed" x-init="failed = $el.complete && $el.naturalWidth === 0" x-on:error="failed = true">
                                    @endif
                                </span>
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
                                class="inline-flex items-center gap-1 px-3 py-2 text-sm font-semibold text-black bg-brand hover:bg-brand-strong rounded-md focus:ring-2 focus:ring-brand-medium transition-colors">
                                Aktivasi PT
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-6 py-8 text-center text-body">
                            Tidak ada paket PT yang belum memiliki coach atau tanggal akhir PT.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        
        <div class="p-4 border-t border-default-medium">
            {{ $this->memberships->links('components.custom-pagination') }}
        </div>
    </div>
    @endif

    <footer class="pt-booking-footer">
        <div class="pt-booking-motto"><p>More than a gym</p><span>A stronger<br>you everyday</span></div>
        <p class="pt-booking-signature">Never Back Down<br><span>Stay Dedicated</span></p>
    </footer>
    </div>


    @if ($showModal && $selectedMembershipId)
        <div class="fixed inset-0 z-50 flex items-center justify-center backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="activation-title">
            <div class="pt-booking-modal rounded-2xl shadow-xl max-w-md w-full mx-4">
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
                            class="px-4 py-2 text-black bg-brand hover:bg-brand-strong rounded-md font-semibold text-sm disabled:opacity-50">Aktivasi PT</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
