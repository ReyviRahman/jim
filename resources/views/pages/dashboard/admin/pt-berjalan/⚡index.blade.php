<?php

use App\Models\Membership;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
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
    public bool $expired = false;

    public function mount(bool $expired = false): void
    {
        $this->expired = $expired;
    }

    private function filterPackages(Builder $query): Builder
    {
        return $this->expired ? $query->recentlyExpiredPt() : $query->runningPt();
    }

    public function boot(): void
    {
        abort_unless(auth()->check() && (in_array(auth()->user()->role, ['admin', 'kasir_gym'], true) || auth()->user()->isHeadCoach()), 403);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function coaches(): LengthAwarePaginator
    {
        return User::query()->where('role', 'pt')
            ->where(fn (Builder $query) => $query->where('is_active', true)
                ->orWhereHas('ptMemberships', fn (Builder $memberships) => $this->filterPackages($memberships)))
            ->when(trim($this->search) !== '', fn (Builder $query) => $query->where('name', 'like', '%'.trim($this->search).'%'))
            ->withCount(['ptMemberships as active_packages_count' => fn (Builder $query) => $this->filterPackages($query)])
            ->orderBy('name')->orderBy('id')->paginate(12);
    }

    #[Computed]
    public function unassignedCount(): int
    {
        return $this->filterPackages(Membership::query())->whereNull('pt_id')->count();
    }
};
?>

<div class="space-y-6">
    <div>
        <h1 class="text-xl font-semibold text-heading">Data PT {{ $expired ? 'Expired' : 'Berjalan' }}</h1>
        <p class="mt-1 text-sm text-body">Pilih coach untuk melihat member dan paket PT yang ditangani.</p>
    </div>

    <div>
        <label for="coach-search" class="mb-2 block text-sm font-medium text-heading">Cari coach</label>
        <input id="coach-search" type="search" wire:model.live.debounce.300ms="search" placeholder="Cari nama coach..." class="w-full max-w-sm rounded-md border border-default-medium bg-white px-3 py-2.5 text-sm text-heading focus:border-brand focus:ring-brand">
    </div>

    @if ($this->unassignedCount > 0)
        <a href="{{ route($expired ? 'admin.pt-expired.unassigned' : 'admin.pt-berjalan.unassigned') }}" wire:navigate class="flex items-center justify-between gap-3 rounded-lg border border-orange-200 bg-orange-50 p-4 text-orange-800 focus-visible:ring-2 focus-visible:ring-orange-500">
            <h2 class="min-w-0 break-words font-semibold">Belum ada coach</h2>
            <span class="shrink-0 text-sm font-medium">{{ $this->unassignedCount }} {{ $expired ? 'paket expired' : 'member aktif' }}</span>
        </a>
    @endif

    <div class="grid grid-cols-1 gap-3">
        @forelse ($this->coaches as $coach)
            <a wire:key="coach-{{ $coach->id }}" href="{{ route($expired ? 'admin.pt-expired.coach' : 'admin.pt-berjalan.coach', ['coach' => $coach->id]) }}" wire:navigate class="flex items-center justify-between gap-3 rounded-lg border border-default bg-white p-4 shadow-xs transition-shadow hover:shadow-md focus-visible:ring-2 focus-visible:ring-brand">
                <div class="flex min-w-0 items-center gap-2">
                    <h2 class="min-w-0 break-words font-semibold text-heading">{{ $coach->name }}</h2>
                    @if (! $coach->is_active)
                        <span class="shrink-0 rounded bg-gray-100 px-2 py-1 text-xs text-gray-600">Nonaktif</span>
                    @endif
                </div>
                <span class="shrink-0 text-sm font-medium text-heading">{{ $coach->active_packages_count }} {{ $expired ? 'paket expired' : 'member aktif' }}</span>
            </a>
        @empty
            <p class="col-span-full rounded-lg border border-default p-8 text-center text-body">{{ $search !== '' ? 'Tidak ada coach yang cocok dengan pencarian.' : 'Belum ada coach.' }}</p>
        @endforelse
    </div>
    {{ $this->coaches->links() }}
</div>
