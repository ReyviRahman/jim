<?php

namespace App\Livewire\Member;

use App\Models\Membership;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::member'), Title('Dashboard Membership')] class extends Component
{
    public ?int $selectedMembershipId = null;

    public function mount(): void
    {
        $this->selectedMembershipId = $this->activeGymMemberships->first()?->getKey();
    }

    public function updatingSelectedMembershipId(mixed $value): void
    {
        $membershipId = filter_var($value, FILTER_VALIDATE_INT);

        abort_unless(
            $membershipId !== false && $this->activeGymMembershipQuery()->whereKey($membershipId)->exists(),
            403,
        );
    }

    public function updatedSelectedMembershipId(mixed $value): void
    {
        $this->selectedMembershipId = (int) $value;
    }

    /** @return EloquentCollection<int, Membership> */
    #[Computed]
    public function activeGymMemberships(): EloquentCollection
    {
        return $this->activeGymMembershipQuery()
            ->with(['gymPackage:id,type,name,category,price,discount,is_active'])
            ->orderByDesc('membership_end_date')
            ->orderByDesc('id')
            ->get();
    }

    #[Computed]
    public function selectedMembership(): ?Membership
    {
        if ($this->selectedMembershipId === null) {
            return null;
        }

        return $this->activeGymMemberships->firstWhere('id', $this->selectedMembershipId);
    }

    /** @return array{name: string, remaining_duration: string}|null */
    #[Computed]
    public function selectedMembershipSummary(): ?array
    {
        $membership = $this->selectedMembership;

        if ($membership === null || $membership->membership_end_date === null) {
            return null;
        }

        return [
            'name' => $membership->gymPackage?->name
                ?? $membership->package_name
                ?? 'Paket Membership',
            'remaining_duration' => $this->remainingDurationLabel($membership->membership_end_date),
        ];
    }

    #[Computed]
    public function ptRemainingSessions(): int
    {
        return (int) $this->accessibleMembershipQuery()
            ->whereIn('type', ['pt', 'bundle_pt_membership'])
            ->where('status', 'active')
            ->where('is_active', true)
            ->whereDate('start_date', '<=', today())
            ->whereDate('pt_end_date', '>=', today())
            ->where('remaining_sessions', '>', 0)
            ->sum('remaining_sessions');
    }

    /** @return EloquentCollection<int, Membership> */
    #[Computed]
    public function ownedPackages(): EloquentCollection
    {
        return $this->accessibleMembershipQuery()
            ->where('status', 'active')
            ->where('is_active', true)
            ->whereDate('start_date', '<=', today())
            ->where(function (Builder $query): void {
                $query->where(function (Builder $gymQuery): void {
                    $gymQuery->whereIn('type', ['membership', 'bundle_pt_membership'])
                        ->whereDate('membership_end_date', '>=', today());
                })->orWhere(function (Builder $ptQuery): void {
                    $ptQuery->whereIn('type', ['pt', 'bundle_pt_membership'])
                        ->whereDate('pt_end_date', '>=', today());
                });
            })
            ->with(['gymPackage:id,name', 'ptPackage:id,name'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();
    }

    /** @return Collection<int, array{id: int, name: string, label: string, starting_price: string, price: string, discount: string, discount_amount: int, total: string}> */
    #[Computed]
    public function ownedPackageSummaries(): Collection
    {
        return $this->ownedPackages->map(function (Membership $membership): array {
            $names = [];

            if (in_array($membership->type, ['membership', 'bundle_pt_membership'], true)) {
                $names[] = $membership->gymPackage?->name;
            }

            if (in_array($membership->type, ['pt', 'bundle_pt_membership'], true)) {
                $names[] = $membership->ptPackage?->name;
            }

            $label = match ($membership->type) {
                'pt' => 'Paket PT Anda',
                'bundle_pt_membership' => 'Paket Bundle Anda',
                default => 'Membership Anda',
            };
            $durationInMonths = $membership->type === 'membership'
                ? $this->packageDurationInMonths($membership->gymPackage?->name ?? $membership->package_name ?? '')
                : null;
            $priceDivisor = $durationInMonths === 1 ? 4 : ($durationInMonths ?? 1);
            $pricePeriod = match (true) {
                $durationInMonths === 1 => ' per minggu',
                $durationInMonths > 1 => ' per bulan',
                default => '',
            };

            return [
                'id' => $membership->id,
                'name' => collect($names)->filter()->join(' / ')
                    ?: ($membership->package_name ?: match ($membership->type) {
                        'pt' => 'Paket PT',
                        'bundle_pt_membership' => 'Paket Bundle',
                        default => 'Paket Membership',
                    }),
                'label' => $label,
                'starting_price' => $this->formatRupiah((int) round(max(0, (int) $membership->price_paid) / $priceDivisor)).$pricePeriod,
                'price' => $this->formatRupiah((int) $membership->base_price),
                'discount' => $this->formatRupiah((int) $membership->discount_applied),
                'discount_amount' => (int) $membership->discount_applied,
                'total' => $this->formatRupiah((int) $membership->price_paid),
            ];
        });
    }

    private function accessibleMembershipQuery(): Builder
    {
        $user = $this->authenticatedUser();

        return Membership::query()
            ->where(function (Builder $query) use ($user): void {
                $query->whereBelongsTo($user, 'user')
                    ->orWhereHas('members', function (Builder $memberQuery) use ($user): void {
                        $memberQuery->whereKey($user->getKey());
                    });
            });
    }

    private function activeGymMembershipQuery(): Builder
    {
        return $this->accessibleMembershipQuery()
            ->whereIn('type', ['membership', 'bundle_pt_membership'])
            ->where('status', 'active')
            ->where('is_active', true)
            ->whereDate('start_date', '<=', today())
            ->whereDate('membership_end_date', '>=', today());
    }

    private function authenticatedUser(): User
    {
        $user = Auth::user();

        abort_unless($user instanceof User, 401);

        return $user;
    }

    private function packageDurationInMonths(string $packageName): ?int
    {
        if (preg_match('/\b(\d+)\s+monthly\s+pass\b/i', $packageName, $matches) === 1) {
            return (int) $matches[1] > 0 ? (int) $matches[1] : null;
        }

        if (preg_match('/\byearly\s+pass\b/i', $packageName) === 1) {
            return 12;
        }

        if (preg_match('/\b(\d+)\s+bulan\s+(?:plus|free)\s+(\d+)\s+bulan\b/i', $packageName, $matches) === 1) {
            return (int) $matches[1] + (int) $matches[2] ?: null;
        }

        if (preg_match('/\b(\d+)\s+(?:plus|free)\s+(\d+)\s+bulan\b/i', $packageName, $matches) === 1) {
            return (int) $matches[1] + (int) $matches[2] ?: null;
        }

        if (preg_match('/\b(\d+)\s+bulan\b/i', $packageName, $matches) === 1) {
            return (int) $matches[1] > 0 ? (int) $matches[1] : null;
        }

        return null;
    }

    private function remainingDurationLabel(CarbonInterface $endDate): string
    {
        $today = today()->startOfDay();
        $endDate = $endDate->copy()->startOfDay();

        if ($today->isSameDay($endDate)) {
            return 'Berakhir hari ini';
        }

        $difference = $today->diff($endDate);
        $months = ($difference->y * 12) + $difference->m;
        $segments = [];

        if ($months > 0) {
            $segments[] = $months.' bulan';
        }

        if ($difference->d > 0 || $segments === []) {
            $segments[] = $difference->d.' hari';
        }

        return implode(' | ', $segments).' tersisa';
    }

    private function formatRupiah(int $amount): string
    {
        return 'Rp '.number_format($amount, 0, ',', '.');
    }
};
?>

<div class="mx-auto w-full max-w-2xl py-4 sm:py-8">
    <div class="space-y-8">
        @if ($this->ptRemainingSessions > 0)
            <div class="flex items-start gap-3 rounded-2xl border border-sky-100 bg-sky-50 px-4 py-4 text-sky-950 sm:px-5" role="status">
                <span class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-full bg-sky-100 text-sky-700">
                    <svg class="size-5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 13V9m0 8h.01M10.3 3.6 2.7 17a2 2 0 0 0 1.7 3h15.2a2 2 0 0 0 1.7-3L13.7 3.6a2 2 0 0 0-3.4 0Z"/>
                    </svg>
                </span>
                <p class="text-sm font-medium leading-6">
                    Sisa sesi PT aktif Anda: {{ $this->ptRemainingSessions }} sesi.
                </p>
            </div>
        @endif

        <section aria-labelledby="operating-hours-title" class="overflow-hidden rounded-3xl bg-[#34342F] shadow-sm">
            <div class="grid sm:grid-cols-[minmax(0,1.15fr)_minmax(0,2fr)]">
                <div class="flex items-center gap-4 bg-yellow-300 px-5 py-5 text-[#34342F] sm:items-start sm:px-6 sm:py-6">
                    <span class="flex size-11 shrink-0 items-center justify-center rounded-full bg-[#34342F] text-yellow-300">
                        <svg class="size-6" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6l4 2m5-2a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                        </svg>
                    </span>
                    <div>
                        <h2 id="operating-hours-title" class="text-lg font-black leading-tight">Jam Operasional</h2>
                        <p class="mt-1 text-sm font-medium text-[#34342F]/70">Atur waktu latihan Anda.</p>
                    </div>
                </div>

                <dl class="grid divide-y divide-white/10 text-white sm:grid-cols-3 sm:divide-x sm:divide-y-0">
                    <div class="flex items-center justify-between gap-4 px-5 py-4 sm:block sm:px-4 sm:py-6">
                        <dt class="text-sm text-gray-300">Senin - Jum’at</dt>
                        <dd class="text-sm font-black sm:mt-2">07.00 - 22.00</dd>
                    </div>
                    <div class="flex items-center justify-between gap-4 px-5 py-4 sm:block sm:px-4 sm:py-6">
                        <dt class="text-sm text-gray-300">Sabtu</dt>
                        <dd class="text-sm font-black sm:mt-2">07.00 - 20.00</dd>
                    </div>
                    <div class="flex items-center justify-between gap-4 px-5 py-4 sm:block sm:px-4 sm:py-6">
                        <dt class="text-sm text-gray-300">Minggu</dt>
                        <dd class="text-sm font-black sm:mt-2">07.00 - 19.00</dd>
                    </div>
                </dl>
            </div>
        </section>

        <section aria-labelledby="current-membership-title">
            <div class="mb-4 flex items-end justify-between gap-4">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.2em] text-gray-400">Status aktif</p>
                    <h2 id="current-membership-title" class="mt-1 text-xl font-black text-gray-950">Membership Saat Ini</h2>
                </div>

                @if ($this->activeGymMemberships->count() > 1)
                    <label class="min-w-0 max-w-56 text-right text-xs font-semibold text-gray-500">
                        <span class="mb-1 block">Pilih membership</span>
                        <select
                            wire:model.live.number="selectedMembershipId"
                            class="block w-full rounded-xl border-gray-200 bg-white px-3 py-2 text-sm font-semibold text-gray-800 shadow-sm focus:border-yellow-400 focus:ring-yellow-400"
                        >
                            @foreach ($this->activeGymMemberships as $membership)
                                <option wire:key="membership-option-{{ $membership->id }}" value="{{ $membership->id }}">
                                    {{ $membership->gymPackage?->name ?? $membership->package_name ?? 'Paket Membership' }}
                                </option>
                            @endforeach
                        </select>
                    </label>
                @endif
            </div>

            @if ($this->selectedMembershipSummary !== null)
                <div class="overflow-hidden rounded-3xl border border-gray-100 bg-linear-to-r from-white via-white to-yellow-50 shadow-sm">
                    <div class="flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:justify-between sm:p-6">
                        <div class="flex min-w-0 items-center gap-4">
                            <span class="flex size-16 shrink-0 items-center justify-center rounded-2xl bg-[#34342F] p-2 shadow-sm">
                                <img src="{{ asset('icon.png') }}" alt="Logo Frans Gym" class="size-12 object-contain">
                            </span>
                            <div class="min-w-0">
                                <p class="text-xs font-bold uppercase tracking-[0.22em] text-yellow-600">Frans Gym Member</p>
                                <p class="mt-1 text-lg font-black leading-tight text-gray-950">{{ $this->selectedMembershipSummary['name'] }}</p>
                            </div>
                        </div>
                        <p class="rounded-2xl bg-white px-4 py-3 text-sm font-black text-[#34342F] shadow-sm ring-1 ring-gray-100 sm:text-right">
                            {{ $this->selectedMembershipSummary['remaining_duration'] }}
                        </p>
                    </div>
                </div>
            @else
                <div class="rounded-3xl border border-dashed border-gray-300 bg-gray-50 px-6 py-8 text-center">
                    <span class="mx-auto flex size-14 items-center justify-center rounded-full bg-white text-gray-400 shadow-sm">
                        <svg class="size-7" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3M5 11h14M5 21h14a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2Z"/>
                        </svg>
                    </span>
                    <h3 class="mt-4 text-lg font-black text-gray-900">Belum ada membership gym aktif</h3>
                    <p class="mt-2 text-sm leading-6 text-gray-500">Paket PT aktif, jika ada, tetap ditampilkan di bawah.</p>
                </div>
            @endif
        </section>

        <section aria-label="Paket aktif Anda" class="space-y-4">
            @forelse ($this->ownedPackageSummaries as $summary)
                <div wire:key="owned-package-{{ $summary['id'] }}" data-testid="owned-package-{{ $summary['id'] }}" class="space-y-4">
                    <details open class="group overflow-hidden rounded-3xl bg-white shadow-lg ring-1 ring-black/5">
                        <summary class="cursor-pointer list-none focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-yellow-300 [&::-webkit-details-marker]:hidden">
                            <span class="relative flex min-h-48 items-center justify-between gap-5 overflow-hidden bg-linear-to-br from-[#171714] via-[#34342F] to-black px-6 py-7 sm:px-8">
                                <span class="absolute -right-12 -top-14 size-40 rounded-full bg-yellow-300/10"></span>
                                <span class="absolute -bottom-20 left-1/3 size-48 rounded-full bg-yellow-400/5"></span>

                                <span class="relative min-w-0">
                                    <span class="block text-xs font-bold uppercase tracking-[0.22em] text-yellow-300">
                                        {{ $summary['label'] }}
                                    </span>
                                    <span class="mt-3 block text-2xl font-black leading-tight text-white sm:text-3xl">
                                        {{ $summary['name'] }}
                                    </span>
                                    <span class="mt-3 block text-sm font-medium text-gray-300">
                                        Mulai {{ $summary['starting_price'] }}
                                    </span>
                                </span>

                                <span class="relative flex size-20 shrink-0 items-center justify-center rounded-3xl bg-white/10 p-2 ring-1 ring-white/10 sm:size-24">
                                    <img src="{{ asset('icon.png') }}" alt="Logo Frans Gym" class="size-full object-contain">
                                </span>
                            </span>

                            <span class="flex items-center justify-center gap-2 bg-yellow-100 px-5 py-3.5 text-sm font-black text-[#34342F] transition group-hover:bg-yellow-200">
                                Lihat Detail
                                <svg class="size-5 transition-transform duration-200 group-open:rotate-180" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="m6 9 6 6 6-6"/>
                                </svg>
                            </span>
                        </summary>

                        <div class="border-t border-gray-100 px-5 py-6 sm:px-7">
                            <h3 class="text-lg font-black text-gray-950">Rincian Harga</h3>

                            <dl class="mt-5 space-y-3 text-sm">
                                <div class="flex items-center justify-between gap-4 text-gray-500">
                                    <dt>Harga Paket</dt>
                                    <dd class="font-semibold text-gray-700">{{ $summary['price'] }}</dd>
                                </div>

                                @if ($summary['discount_amount'] > 0)
                                    <div class="flex items-center justify-between gap-4 text-emerald-700">
                                        <dt>Diskon</dt>
                                        <dd class="font-semibold">-{{ $summary['discount'] }}</dd>
                                    </div>
                                @endif

                                <div class="flex items-center justify-between gap-4 border-t border-gray-200 pt-4 text-base">
                                    <dt class="font-black text-gray-950">Total Pembayaran</dt>
                                    <dd class="font-black text-gray-950">{{ $summary['total'] }}</dd>
                                </div>
                            </dl>
                        </div>
                    </details>

                    <div class="flex items-center justify-between gap-4 rounded-3xl border border-gray-100 bg-white px-5 py-5 shadow-sm sm:px-7">
                        <div>
                            <p class="text-sm font-medium text-gray-500">Total Harga</p>
                            <p class="mt-1 text-2xl font-black tracking-tight text-[#34342F]">{{ $summary['total'] }}</p>
                        </div>
                        <span class="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-yellow-300 text-[#34342F]">
                            <svg class="size-6" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h2M5 19h14a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2Z"/>
                            </svg>
                        </span>
                    </div>
                </div>
            @empty
                <div class="rounded-3xl border border-gray-200 bg-gray-50 px-6 py-7 text-center">
                    <h3 class="text-lg font-black text-gray-950">Belum ada membership atau PT aktif</h3>
                    <p class="mt-2 text-sm leading-6 text-gray-500">Silakan hubungi admin Frans Gym untuk informasi paket Anda.</p>
                </div>
            @endforelse
        </section>
    </div>
</div>
