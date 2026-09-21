<?php

namespace App\Livewire\Admin;

use App\Models\Membership;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::admin')] class extends Component
{
    use WithPagination;

    public string $search = '';

    #[Locked]
    public ?int $selectedMembershipId = null;

    public function boot(): void
    {
        abort_unless(auth()->check() && (in_array(auth()->user()->role, ['admin', 'kasir_gym'], true) || auth()->user()->isHeadCoach()), 403);
    }

    public function openDetailModal(int $membershipId): void
    {
        abort_unless($this->membershipQuery()->whereKey($membershipId)->exists(), 404);
        $this->selectedMembershipId = $membershipId;
        unset($this->selectedMembership);
    }

    public function closeDetailModal(): void
    {
        $this->selectedMembershipId = null;
        unset($this->selectedMembership);
    }

    #[Computed]
    public function selectedMembership(): ?Membership
    {
        return $this->selectedMembershipId === null ? null : $this->membershipQuery()->find($this->selectedMembershipId);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    /** @return array{start: \Illuminate\Support\Carbon, end: \Illuminate\Support\Carbon} */
    #[Computed]
    public function dateRange(): array
    {
        $today = today('Asia/Jakarta');

        return ['start' => $today->copy()->subDays(30), 'end' => $today->copy()->subDay()];
    }

    private function membershipQuery(): Builder
    {
        return Membership::with(['user', 'members', 'gymPackage', 'ptPackage'])
            ->whereBetween('membership_end_date', [
                $this->dateRange['start']->toDateString(),
                $this->dateRange['end']->toDateString(),
            ]);
    }

    #[Computed]
    public function memberships(): LengthAwarePaginator
    {
        return $this->membershipQuery()
            ->when($this->search !== '', function (Builder $query): void {
                $matchesContact = function (Builder $contact): void {
                    $contact->where(function (Builder $fields): void {
                        $fields->where('name', 'like', '%'.$this->search.'%')
                            ->orWhere('email', 'like', '%'.$this->search.'%')
                            ->orWhere('phone', 'like', '%'.$this->search.'%');
                    });
                };

                $query->where(function (Builder $people) use ($matchesContact): void {
                    $people->whereHas('user', $matchesContact)
                        ->orWhereHas('members', $matchesContact);
                });
            })
            ->orderByDesc('membership_end_date')
            ->orderByDesc('id')
            ->paginate(20);
    }
};
?>

<div>
    <div class="flex sm:flex-row flex-col justify-between items-center mb-6">
        <div>
            <h5 class="text-xl font-semibold text-heading">Member Expired</h5>
            <p class="text-sm text-body">Membership berakhir {{ $this->dateRange['start']->format('d M Y') }} sampai {{ $this->dateRange['end']->format('d M Y') }} (30 hari terakhir).</p>
        </div>
    </div>

    @if (session()->has('success'))
        <div class="p-4 mb-4 text-sm text-green-800 rounded-lg bg-green-50" role="alert">
            <span class="font-medium">Sukses!</span> {{ session('success') }}
        </div>
    @endif

    <div class="relative overflow-hidden bg-neutral-primary-soft shadow-xs rounded-md border border-default mb-6">
        <div class="p-4 flex flex-col md:flex-row items-center justify-between gap-4">
            <div class="relative w-full md:w-auto md:flex-1">
                <div class="absolute inset-y-0 start-0 flex items-center ps-3 pointer-events-none">
                    <svg class="w-4 h-4 text-body" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-width="2" d="m21 21-3.5-3.5M17 10a7 7 0 1 1-14 0 7 7 0 0 1 14 0Z"/></svg>
                </div>
                <input type="text" wire:model.live.debounce.300ms="search" class="block w-full max-w-sm ps-9 pe-3 py-2.5 bg-white border border-default-medium text-heading text-sm rounded-md focus:ring-brand focus:border-brand shadow-xs placeholder:text-body" placeholder="Cari nama, email, atau no. hp...">
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 xl:grid-cols-3 gap-2">
                @forelse ($this->memberships as $membership)
                    <button type="button" wire:key="membership-{{ $membership->id }}" wire:click="openDetailModal({{ $membership->id }})" aria-haspopup="dialog"
                        class="min-w-0 text-left bg-neutral-primary-soft rounded-lg border border-default shadow-sm p-2 hover:shadow-md transition-shadow cursor-pointer focus-visible:ring-2 focus-visible:ring-brand">
                        <span class="block font-bold text-heading text-base truncate">{{ $membership->members->isNotEmpty() ? $membership->members->pluck('name')->implode(', ') : ($membership->user?->name ?? '-') }}</span>
                        <span class="block space-y-2 mt-2">
                            <span class="block space-y-1 text-xs text-gray-500">
                                <span class="block"><span class="font-medium">Tanggal Mulai Paket:</span> {{ $membership->start_date?->format('d M Y') ?? '-' }}</span>
                                <span class="block"><span class="font-medium">Tanggal Membership Berakhir:</span> {{ $membership->membership_end_date->format('d M Y') }}</span>
                            </span>
                            <span class="flex justify-between items-center gap-2 text-xs">
                                <span class="font-medium text-indigo-700 truncate">{{ $membership->package_name ?? $membership->gymPackage?->name ?? $membership->ptPackage?->name ?? '-' }}</span>
                                <span class="font-medium text-red-600">Expired</span>
                            </span>
                            <span class="block text-xs text-gray-500"><span class="font-medium">Pemilik:</span> {{ $membership->user?->name ?? '-' }}</span>
                        </span>
                    </button>
                @empty
                    <div class="col-span-full py-8 text-center text-gray-500 bg-neutral-primary-soft rounded-lg border border-default">
                            Tidak ada membership expired dalam 30 hari terakhir yang sesuai pencarian.
                    </div>
                @endforelse
        </div>
    </div>

    <div class="mb-6">
        {{ $this->memberships->links() }}
    </div>

    @if ($this->selectedMembership)
        @php($detail = $this->selectedMembership)
        <div x-data x-init="$nextTick(() => $refs.close.focus())" x-on:keydown.escape.window="$wire.closeDetailModal()" wire:click.self="closeDetailModal"
            class="fixed inset-0 z-50 flex items-center justify-center backdrop-blur-sm">
            <section role="dialog" aria-modal="true" aria-labelledby="member-expired-detail-title" x-trap.inert.noscroll="true"
                class="bg-white rounded-lg shadow-xl max-w-lg w-full mx-4 max-h-[90vh] overflow-y-auto">
                <div class="p-6 border-b border-default-medium flex items-center justify-between">
                    <h3 id="member-expired-detail-title" class="text-lg font-semibold text-heading">Detail Member Expired</h3>
                    <button x-ref="close" type="button" wire:click="closeDetailModal" aria-label="Tutup detail" class="text-body hover:text-heading">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <div class="p-6 space-y-4">
                    <div class="bg-neutral-secondary-medium p-4 rounded-md">
                        <p class="text-xs text-gray-500 uppercase font-bold mb-1">Nama Pemilik</p>
                        <p class="font-semibold text-heading">{{ $detail->user?->name ?? '-' }}</p>
                        <dl class="mt-3 space-y-2 text-sm">
                            <div><dt class="text-gray-500">Email</dt><dd class="text-heading break-words">{{ $detail->user?->email ?? '-' }}</dd></div>
                            <div><dt class="text-gray-500">No. HP</dt><dd class="text-heading">{{ $detail->user?->phone ?? '-' }}</dd></div>
                        </dl>
                    </div>
                    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                        <div class="sm:col-span-2"><dt class="text-gray-500">Nama Paket</dt><dd class="font-semibold text-indigo-700">{{ $detail->package_name ?? $detail->gymPackage?->name ?? $detail->ptPackage?->name ?? '-' }}</dd></div>
                        <div><dt class="text-gray-500">Tanggal Mulai Paket</dt><dd class="font-medium text-heading">{{ $detail->start_date?->format('d M Y') ?? '-' }}</dd></div>
                        <div><dt class="text-gray-500">Tanggal Membership Berakhir</dt><dd class="font-medium text-red-600">{{ $detail->membership_end_date->format('d M Y') }}</dd></div>
                        <div class="sm:col-span-2"><dt class="text-gray-500">Anggota Paket</dt><dd class="font-medium text-heading">{{ $detail->members->pluck('name')->implode(', ') ?: '-' }}</dd></div>
                    </dl>
                </div>
                <div class="p-6 border-t border-default-medium flex justify-end">
                    <button type="button" wire:click="closeDetailModal" class="px-4 py-2 text-heading bg-neutral-secondary-medium border border-default-medium rounded-md hover:bg-neutral-secondary-strong font-medium text-sm">Tutup</button>
                </div>
            </section>
        </div>
    @endif
</div>
