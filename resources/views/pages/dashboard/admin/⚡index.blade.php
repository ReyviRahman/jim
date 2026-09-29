<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Livewire\Attributes\Layout;
use App\Models\Membership;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

new #[Layout('layouts::admin')] class extends Component
{
    public function getDoubleMembershipsProperty()
    {
        // Ambil semua membership dengan status != completed beserta user_id
        $mainMemberships = Membership::where('status', '!=', 'completed')
            ->get(['user_id', 'id']);

        // Ambil semua pivot membership dengan status != completed
        $pivotMemberships = DB::table('membership_users')
            ->join('memberships', 'membership_users.membership_id', '=', 'memberships.id')
            ->where('memberships.status', '!=', 'completed')
            ->select('membership_users.user_id', 'membership_users.membership_id as id')
            ->get();

        // Gabungkan dan hitung membership unik per user
        $doubleUserIds = collect($mainMemberships)
            ->merge($pivotMemberships)
            ->mapToGroups(function ($item): array {
                return [$item->user_id => $item->id];
            })
            ->filter(fn ($membershipIds): bool => $membershipIds->unique()->count() > 1)
            ->keys();

        // Ambil semua membership di mana user dobel terdaftar (sebagai user_id utama atau anggota)
        return Membership::where('status', '!=', 'completed')
            ->where(function ($query) use ($doubleUserIds): void {
                $query->whereIn('user_id', $doubleUserIds)
                    ->orWhereHas('members', function ($q) use ($doubleUserIds): void {
                        $q->whereIn('user_id', $doubleUserIds);
                    });
            })
            ->with(['user', 'members', 'admin', 'followUp', 'followUpTwo', 'personalTrainer', 'gymPackage', 'ptPackage'])
            ->orderBy('user_id')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /** @return array{labels: list<string>, data: list<int>, percentages: list<float>, colors: list<string>, total: int} */
    public function getChartDataProperty(): array
    {
        $installments = Membership::query()->where(fn (Builder $query) => $query
            ->where(fn (Builder $gym) => $gym->installments())
            ->orWhere(fn (Builder $pt) => $pt->installments(ptOnly: true)));
        $awaitingActivation = Membership::query()->where(fn (Builder $query) => $query
            ->where(fn (Builder $gym) => $gym->awaitingGymActivation())
            ->orWhere(fn (Builder $pt) => $pt->awaitingPtOnboarding()));
        $active = Membership::query()->where(fn (Builder $query) => $query
            ->where(fn (Builder $gym) => $gym->activeGym())
            ->orWhere(fn (Builder $pt) => $pt->runningPt()));

        $counts = [
            $active->whereNotIn('id', (clone $installments)->select('id'))
                ->whereNotIn('id', (clone $awaitingActivation)->select('id'))->count(),
            $awaitingActivation->whereNotIn('id', (clone $installments)->select('id'))->count(),
            $installments->count(),
        ];
        $total = array_sum($counts);

        return [
            'labels' => ['Aktif', 'Belum Aktif', 'Cicilan'],
            'data' => $counts,
            'percentages' => array_map(fn (int $count): float => $total > 0 ? round($count / $total * 100, 1) : 0.0, $counts),
            'colors' => ['#10B981', '#F59E0B', '#EF4444'],
            'total' => $total,
        ];
    }

};
?>

<div>
    <div class="flex sm:flex-row flex-col justify-between items-center mb-6">
        <h5 class="text-xl font-semibold text-heading">Dashboard Membership</h5>
    </div>

    @php
        $chartData = $this->chartData;
    @endphp
    <section aria-labelledby="package-status-title" class="mb-6 rounded-base border border-default bg-neutral-primary-soft p-4 shadow-xs sm:p-6">
        <h2 id="package-status-title" class="text-lg font-semibold text-heading">Status Paket Saat Ini</h2>
        <p class="mt-1 text-sm text-body">Total: <strong>{{ number_format($chartData['total'], 0, ',', '.') }} paket</strong>. Setiap paket dihitung sekali.</p>
        <div class="mt-6 grid items-center gap-6 md:grid-cols-2">
            @if ($chartData['total'] > 0)
                <div class="relative h-72 min-w-0 sm:h-80" wire:ignore
                    x-data="{
                        destroyChart: null,
                        init() {
                            const data = @js($chartData);
                            const chart = new Chart(this.$refs.canvas.getContext('2d'), {
                                type: 'pie',
                                data: {
                                    labels: data.labels,
                                    datasets: [{ data: data.data, backgroundColor: data.colors, borderWidth: 2, borderColor: '#ffffff' }]
                                },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    plugins: {
                                        legend: { display: false },
                                        tooltip: {
                                            callbacks: {
                                                label(context) {
                                                    const percent = data.percentages[context.dataIndex].toLocaleString('id-ID', { minimumFractionDigits: 1, maximumFractionDigits: 1 });
                                                    return context.label + ': ' + context.raw.toLocaleString('id-ID') + ' paket (' + percent + '%)';
                                                }
                                            }
                                        }
                                    }
                                }
                            });
                            this.destroyChart = () => chart.destroy();
                        },
                        destroy() { this.destroyChart?.(); }
                    }">
                    <canvas x-ref="canvas" role="img" aria-label="Pie chart status paket: Aktif, Belum Aktif, dan Cicilan. Jumlah dan persentase tersedia pada legenda."></canvas>
                </div>
            @else
                <p role="status" class="py-16 text-center text-body">Belum ada data paket</p>
            @endif
            <ul class="space-y-4" aria-label="Jumlah dan persentase status paket">
                @foreach ($chartData['labels'] as $index => $label)
                    <li class="flex flex-wrap items-center justify-between gap-3 rounded-md border border-default p-4">
                        <span class="flex items-center gap-3 text-heading"><span aria-hidden="true" class="size-3 shrink-0 rounded-full" style="background-color: {{ $chartData['colors'][$index] }}"></span>{{ $label }}</span>
                        <span class="text-sm text-body">{{ number_format($chartData['data'][$index], 0, ',', '.') }} paket <strong class="ml-2 text-heading">{{ number_format($chartData['percentages'][$index], 1, ',', '.') }}%</strong></span>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>

    {{-- Tabel Membership Dobel --}}
    <div class="mb-6">
        <div class="flex sm:flex-row flex-col justify-between items-center mb-4">
            <h5 class="text-lg font-semibold text-heading">Membership Dobel (User dengan &gt;1 Membership Aktif)</h5>
        </div>

        <div class="relative overflow-hidden bg-neutral-primary-soft shadow-xs rounded-md border border-default">
            <table data-responsive-table data-responsive-breakpoint="xl" class="table-fixed w-full text-sm text-left rtl:text-right text-body">
                <thead class="text-sm text-body bg-neutral-secondary-medium border-b border-default-medium">
                    <tr>
                        <th scope="col" class="px-6 py-3 font-medium">No</th>
                        <th scope="col" class="px-6 py-3 font-medium">Member</th>
                        <th scope="col" class="px-6 py-3 font-medium">Program / Paket</th>
                        <th scope="col" class="px-6 py-3 font-medium text-right">Total Bayar</th>
                        <th scope="col" class="px-6 py-3 font-medium">Masa Aktif</th>
                        <th scope="col" class="px-6 py-3 font-medium text-center">Status</th>
                        <th scope="col" class="px-6 py-3 font-medium text-center">Admin Follow Up</th>
                        <th scope="col" class="px-6 py-3 font-medium text-center">Sales Follow Up</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->doubleMemberships as $membership)
                        <tr wire:key="double-{{ $membership->id }}" class="bg-neutral-primary-soft border-b border-default hover:bg-neutral-secondary-medium">
                            <td class="px-6 py-4 font-medium text-heading">
                                {{ $loop->iteration }}
                            </td>
                            <td class="px-6 py-4 font-medium text-heading">
                                <div class="flex flex-col gap-1.5">
                                    @forelse($membership->members as $member)
                                        <div class="flex items-center gap-2">
                                            <span class="font-semibold">{{ $member->name }}</span>
                                        </div>
                                    @empty
                                        <div class="font-semibold">{{ $membership->user->name ?? 'N/A' }}</div>
                                    @endforelse
                                </div>
                            </td>
                            <td class="px-6 py-4 text-heading">
                                <div class="flex flex-col gap-2">
                                    @if(in_array($membership->type, ['membership', 'bundle_pt_membership', 'visit']))
                                        <div>
                                            <div class="text-[10px] text-gray-400 uppercase tracking-wider font-bold mb-0.5">
                                                Paket {{ $membership->type === 'visit' ? 'Harian' : 'Gym' }}
                                            </div>
                                            <div class="font-medium {{ $membership->type === 'visit' ? 'text-orange-600' : 'text-emerald-600' }}">
                                                {{ $membership->gymPackage->name ?? 'Paket Terhapus' }}
                                            </div>
                                        </div>
                                    @endif
                                    @if(in_array($membership->type, ['pt', 'bundle_pt_membership']))
                                        <div class="{{ in_array($membership->type, ['bundle_pt_membership']) ? 'border-t border-gray-200 pt-2' : '' }}">
                                            <div class="text-[10px] text-gray-400 uppercase tracking-wider font-bold mb-0.5">Paket Trainer</div>
                                            <div class="font-medium text-indigo-600">{{ $membership->ptPackage->name ?? 'Paket Terhapus' }}</div>
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
                            <td class="px-6 py-4 text-right">
                                @if($membership->discount_applied > 0)
                                    @php
                                        $originalPrice = $membership->price_paid + $membership->discount_applied;
                                        $percentage = ($originalPrice > 0) ? ($membership->discount_applied / $originalPrice) * 100 : 0;
                                    @endphp
                                    <div class="flex flex-col items-end mb-1">
                                        <div class="flex items-center gap-2">
                                            <span class="text-xs text-gray-400 line-through">Rp {{ number_format($originalPrice, 0, ',', '.') }}</span>
                                            <span class="bg-green-100 text-green-800 text-[10px] font-bold px-1.5 py-0.5 rounded">
                                                -{{ is_float($percentage) ? round($percentage, 1) : $percentage }}%
                                            </span>
                                        </div>
                                        <div class="text-[10px] text-green-600 font-medium mt-0.5">
                                            Diskon Rp {{ number_format($membership->discount_applied, 0, ',', '.') }}
                                        </div>
                                    </div>
                                @endif
                                <div class="font-bold text-heading text-base">
                                    Rp {{ number_format($membership->price_paid, 0, ',', '.') }}
                                </div>
                                @if(auth()->check() && auth()->user()->role === 'admin')
                                    @php
                                        $priceLabelData = $membership->getPriceLabel();
                                    @endphp
                                    @if($priceLabelData)
                                        <div class="mt-1 flex justify-end">
                                            <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full {{ $priceLabelData['color'] }}">
                                                {{ $priceLabelData['label'] }}
                                            </span>
                                        </div>
                                    @endif
                                @endif
                            </td>
                            <td class="px-6 py-4 text-xs">
                                <div class="flex flex-col gap-1.5">
                                    <div class="flex items-center text-gray-600">
                                        <svg class="w-3.5 h-3.5 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                        Mulai: <span class="font-medium text-heading ml-1">{{ $membership->start_date ? $membership->start_date->format('d M Y') : 'BELUM AKTIF' }}</span>
                                    </div>
                                    @if(in_array($membership->type, ['membership', 'bundle_pt_membership']))
                                        <div class="flex items-center text-gray-600">
                                            <span class="inline-block w-2 h-2 rounded-full bg-emerald-400 mr-2"></span>
                                            Gym s/d: <span class="font-medium text-emerald-600 ml-1">{{ $membership->membership_end_date ? $membership->membership_end_date->format('d M Y') : 'BELUM AKTIF' }}</span>
                                        </div>
                                    @endif
                                    @if($membership->type === 'visit')
                                        <div class="flex items-center text-gray-600 mt-0.5">
                                            <span class="inline-block w-2 h-2 rounded-full bg-orange-400 mr-2"></span>
                                            <span class="font-medium text-orange-600 ml-1">Berlaku 1 Hari</span>
                                        </div>
                                    @endif
                                    @if(in_array($membership->type, ['pt', 'bundle_pt_membership']))
                                        <div class="flex items-center text-gray-600">
                                            <span class="inline-block w-2 h-2 rounded-full bg-indigo-400 mr-2"></span>
                                            PT s/d: <span class="font-medium text-indigo-600 ml-1">{{ $membership->pt_end_date ? $membership->pt_end_date->format('d M Y') : 'BELUM AKTIF' }}</span>
                                        </div>
                                    @endif
                                </div>
                            </td>
                            <td class="px-6 py-4 text-center">
                                @php
                                    $statusColor = match($membership->status) {
                                        'active' => 'bg-green-100 text-green-800',
                                        'pending' => 'bg-yellow-100 text-yellow-800',
                                        'expired' => 'bg-red-100 text-red-800',
                                        'cancelled' => 'bg-gray-100 text-gray-800',
                                        default => 'bg-blue-100 text-blue-800'
                                    };
                                    $statusLabel = match($membership->status) {
                                        'active' => 'Aktif',
                                        'pending' => 'Menunggu',
                                        'expired' => 'Kadaluarsa',
                                        'cancelled' => 'Dibatalkan',
                                        default => ucfirst($membership->status)
                                    };
                                @endphp
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $statusColor }}">
                                    {{ $statusLabel }}
                                </span>
                            </td>
                            <td class="px-6 py-4 font-medium text-heading text-center">
                                <span class="font-semibold">{{ $membership->followUp->name ?? '-' }}</span>
                            </td>
                            <td class="px-6 py-4 font-medium text-heading text-center">
                                <span class="font-semibold">{{ $membership->followUpTwo->name ?? '-' }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-6 py-8 text-center text-gray-500">
                                Tidak ada membership dobel.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
