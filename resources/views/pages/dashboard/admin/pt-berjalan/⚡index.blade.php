<?php

use App\Models\Membership;
use App\Models\User;
use Carbon\CarbonImmutable;
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
    public string $periodStart = '';

    #[Locked]
    public string $periodEnd = '';

    #[Locked]
    public bool $expired = false;

    public function mount(bool $expired = false): void
    {
        $this->expired = $expired;
        if (! $this->expired) {
            $this->initializePeriod();
        }
    }

    private function initializePeriod(): void
    {
        $today = CarbonImmutable::today(config('app.timezone'));
        $month = $today->startOfMonth();
        $startMonth = $today->day >= 16 ? $month : $month->subMonth();
        $this->periodStart = $startMonth->day(16)->toDateString();
        $this->periodEnd = $startMonth->addMonth()->day(15)->toDateString();
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

<div @class(['pt-expired-page' => $expired, 'pt-running-page' => ! $expired])>
    @if ($expired)
        <img src="{{ asset('member-attendance-gym-v2.png') }}" alt="" class="pt-expired-backdrop" aria-hidden="true">
        @include('pages.dashboard.admin.pt-berjalan.expired')
    @else
        <img src="{{ asset('member-attendance-gym-v2.png') }}" alt="" class="pt-running-backdrop" aria-hidden="true">
        @include('pages.dashboard.admin.pt-berjalan.running')
    @endif
</div>
