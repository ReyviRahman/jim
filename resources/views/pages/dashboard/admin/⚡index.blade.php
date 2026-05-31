<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Livewire\Attributes\Layout;
use App\Models\Membership;
use App\Models\MembershipTransaction;
use Illuminate\Support\Facades\DB;

new #[Layout('layouts::admin')] class extends Component
{
    public $dateStart;
    public $dateEnd;

    public function mount()
    {
        // Default: tanggal 15 bulan lalu hingga 15 bulan ini
        $this->dateStart = now()->copy()->subMonth()->setDay(15)->format('Y-m-d');
        $this->dateEnd = now()->copy()->setDay(15)->format('Y-m-d');
    }

    public function setDateRange($rangeStr)
    {
        if (str_contains($rangeStr, ' to ')) {
            $dates = explode(' to ', $rangeStr);
            $this->dateStart = $dates[0];
            $this->dateEnd = $dates[1];
        } elseif ($rangeStr) {
            $this->dateStart = $rangeStr;
            $this->dateEnd = $rangeStr;
        } else {
            $this->dateStart = null;
            $this->dateEnd = null;
        }

        $this->dispatch('chartDataUpdated', $this->chartData);
    }

    public function getFormattedDateRangeProperty(): string
    {
        if (! $this->dateStart || ! $this->dateEnd) {
            return 'Semua Waktu';
        }

        $bulanIndonesia = [
            1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
            'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
        ];

        $start = \Carbon\Carbon::parse($this->dateStart);
        $end = \Carbon\Carbon::parse($this->dateEnd);

        $format = function (\Carbon\Carbon $date) use ($bulanIndonesia): string {
            return $date->day.' '.$bulanIndonesia[(int) $date->month].' '.$date->year;
        };

        return $format($start).' - '.$format($end);
    }

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

    public function getChartDataProperty()
    {
        // Subquery untuk ambil payment_date terakhir per membership
        $lastPaymentSubquery = MembershipTransaction::select('membership_id', DB::raw('MAX(payment_date) as last_payment_date'))
            ->groupBy('membership_id');

        // Base query dengan join subquery
        $baseQuery = Membership::query()
            ->joinSub($lastPaymentSubquery, 'last_transactions', function ($join) {
                $join->on('memberships.id', '=', 'last_transactions.membership_id');
            });

        // Filter berdasarkan range tanggal jika ada
        if ($this->dateStart && $this->dateEnd) {
            $baseQuery->whereBetween('last_transactions.last_payment_date', [
                $this->dateStart . ' 00:00:00',
                $this->dateEnd . ' 23:59:59'
            ]);
        }

        // Hitung per kategori
        $aktif = (clone $baseQuery)
            ->where('memberships.payment_status', 'paid')
            ->where('memberships.is_active', true)
            ->count();

        $belumAktif = (clone $baseQuery)
            ->where('memberships.payment_status', 'paid')
            ->where('memberships.is_active', false)
            ->count();

        $cicilan = (clone $baseQuery)
            ->where('memberships.payment_status', 'partial')
            ->where('memberships.is_active', false)
            ->count();

        $memberIdsWithActiveMembership = Membership::where('status', '!=', 'completed')
            ->pluck('user_id')
            ->merge(
                DB::table('membership_users')
                    ->join('memberships', 'membership_users.membership_id', '=', 'memberships.id')
                    ->where('memberships.status', '!=', 'completed')
                    ->pluck('membership_users.user_id')
            )
            ->unique()
            ->values();

        $tidakAktif = \App\Models\User::where('role', 'member')
            ->whereNotIn('id', $memberIdsWithActiveMembership)
            ->count();

        return [
            'labels' => ['Aktif', 'Belum Aktif', 'Cicilan', 'Tidak Aktif'],
            'data' => [$aktif, $belumAktif, $cicilan, $tidakAktif],
            'colors' => ['#10B981', '#F59E0B', '#EF4444', '#6B7280'],
        ];
    }
};
?>

<div>
    <div class="flex sm:flex-row flex-col justify-between items-center mb-6">
        <h5 class="text-xl font-semibold text-heading">Dashboard Membership</h5>
    </div>

    {{-- Filter Tanggal --}}
    <div class="flex flex-col sm:flex-row sm:items-center gap-3 mb-6">
        <div class="relative w-full md:w-56" wire:ignore>
            <div class="absolute inset-y-0 start-0 flex items-center ps-3 pointer-events-none">
                <svg class="w-4 h-4 text-body" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 10h16M8 14h8m-4-7V4M7 7V4m10 3V4M5 20h14a1 1 0 0 0 1-1V7a1 1 0 0 0-1-1H5a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1Z"/></svg>
            </div>
            <input type="text" x-data 
                x-init="flatpickr($el, { 
                    mode: 'range', 
                    dateFormat: 'Y-m-d',
                    defaultDate: ['{{ $dateStart }}', '{{ $dateEnd }}'],
                    placeholder: 'Pilih Tanggal',
                    onClose: function(selectedDates, dateStr, instance) { 
                        @this.call('setDateRange', dateStr) 
                    }
                })" 
                class="block w-full ps-9 pe-3 py-2.5 bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base focus:ring-brand focus:border-brand shadow-xs placeholder:text-body" 
                placeholder="Pilih Rentang Tanggal">
        </div>
        <p class="text-sm text-body">
            Periode: <span class="font-medium text-heading">{{ $this->formattedDateRange }}</span>
        </p>
    </div>

    {{-- Chart Container --}}
    <div class="bg-neutral-primary-soft border border-default rounded-base shadow-xs p-6 mb-6">
        <canvas id="membershipChart" width="400" height="200"></canvas>
    </div>

    {{-- Tabel Membership Dobel --}}
    <div class="mb-6">
        <div class="flex sm:flex-row flex-col justify-between items-center mb-4">
            <h5 class="text-lg font-semibold text-heading">Membership Dobel (User dengan &gt;1 Membership Aktif)</h5>
        </div>

        <div class="relative overflow-x-auto bg-neutral-primary-soft shadow-xs rounded-md border border-default">
            <table class="w-full text-sm text-left rtl:text-right text-body">
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
                            <td class="px-6 py-4 font-medium text-heading whitespace-nowrap">
                                {{ $loop->iteration }}
                            </td>
                            <td class="px-6 py-4 font-medium text-heading whitespace-nowrap">
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
                            <td class="px-6 py-4 text-heading whitespace-nowrap">
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
                            <td class="px-6 py-4 text-right whitespace-nowrap">
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
                            <td class="px-6 py-4 whitespace-nowrap text-xs">
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
                            <td class="px-6 py-4 whitespace-nowrap text-center">
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
                            <td class="px-6 py-4 font-medium text-heading whitespace-nowrap text-center">
                                <span class="font-semibold">{{ $membership->followUp->name ?? '-' }}</span>
                            </td>
                            <td class="px-6 py-4 font-medium text-heading whitespace-nowrap text-center">
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

    {{-- Chart.js CDN --}}
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <script>
        document.addEventListener('livewire:initialized', function () {
            let chart = null;

            function initChart(data) {
                const ctx = document.getElementById('membershipChart').getContext('2d');
                
                if (chart) {
                    chart.destroy();
                }

                chart = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: data.labels,
                        datasets: [{
                            label: 'Jumlah Membership',
                            data: data.data,
                            backgroundColor: data.colors,
                            borderColor: data.colors,
                            borderWidth: 1,
                            borderRadius: 8,
                            barThickness: 60,
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        var suffix = context.label === 'Tidak Aktif' ? ' User' : ' Membership';
                                        return context.parsed.y + suffix;
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    stepSize: 1,
                                    precision: 0
                                },
                                grid: {
                                    color: 'rgba(0, 0, 0, 0.1)'
                                }
                            },
                            x: {
                                grid: {
                                    display: false
                                }
                            }
                        }
                    }
                });
            }

            // Initial load
            const initialData = @json($this->chartData);
            initChart(initialData);

            // Update on Livewire updates
            Livewire.on('chartDataUpdated', function (data) {
                initChart(data[0]);
            });
        });
    </script>
</div>