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
            ->withCount(['ptBookings as attended_sessions' => function (Builder $query): void {
                $query->where('attendance', 'attended');
            }])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @return Collection<int, array{
     *     id: int, name: string, label: string,
     *     has_gym: bool, has_pt: bool, gym_active: bool, pt_active: bool,
     *     total_sessions: int, remaining_sessions: int, attended_sessions: int, progress: int,
     *     gym_end: string, pt_end: string, gym_duration: string,
     *     starting_price: string, price: string, discount: string, discount_amount: int, total: string
     * }>
     */
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

            $hasGym = in_array($membership->type, ['membership', 'bundle_pt_membership'], true);
            $hasPt = in_array($membership->type, ['pt', 'bundle_pt_membership'], true);
            $totalSessions = max(0, (int) $membership->total_sessions);
            $attendedSessions = max(0, (int) $membership->attended_sessions);

            return [
                'has_gym' => $hasGym,
                'has_pt' => $hasPt,
                'total_sessions' => $totalSessions,
                'remaining_sessions' => max(0, (int) $membership->remaining_sessions),
                'attended_sessions' => $attendedSessions,
                'progress' => $totalSessions > 0 ? min(100, (int) round($attendedSessions / $totalSessions * 100)) : 0,
                'gym_active' => $hasGym && $membership->membership_end_date?->greaterThanOrEqualTo(today()),
                'pt_active' => $hasPt && $membership->pt_end_date?->greaterThanOrEqualTo(today()),
                'gym_end' => $membership->membership_end_date?->locale('id')->translatedFormat('d M Y') ?? 'Belum ditentukan',
                'pt_end' => $membership->pt_end_date?->locale('id')->translatedFormat('d M Y') ?? 'Belum ditentukan',
                'gym_duration' => $membership->membership_end_date !== null
                    ? ($membership->membership_end_date->isBefore(today()) ? 'Masa aktif berakhir' : $this->remainingDurationLabel($membership->membership_end_date))
                    : 'Belum ditentukan',
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

<main class="member-home mx-auto w-full max-w-[1200px]">
    <h1 class="sr-only">Paket dan kehadiran member</h1>
    <div class="space-y-10 sm:space-y-16">
        @forelse ($this->ownedPackageSummaries as $summary)
            <x-member-package-card :summary="$summary" :first="$loop->first" wire:key="owned-package-{{ $summary['id'] }}" />
        @empty
            <x-member-empty-package />
        @endforelse
    </div>
    @if ($this->ownedPackages->isNotEmpty())
    <footer class="flex items-center gap-5 px-5 py-8 sm:px-9 sm:py-10" aria-label="Frans Gym">
        <div class="shrink-0 text-white">
            <p class="text-lg font-black tracking-tight">FRANSGYM</p>
            <p class="text-[8px] font-semibold tracking-[0.35em]">FITNESS JAMBI</p>
        </div>
        <span class="h-px grow bg-gray-800" aria-hidden="true"></span>
        <p class="text-[8px] leading-5 tracking-[0.25em] text-gray-300">NEVERBACKDOWN<br>STAYDEDICATED</p>
    </footer>
    @endif
</main>
