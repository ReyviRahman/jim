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
        // Default: tanggal 15 bulan ini hingga 15 bulan ini
        $this->dateStart = now()->copy()->setDay(15)->format('Y-m-d');
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
                $this->dateStart,
                $this->dateEnd
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

        return [
            'labels' => ['Aktif', 'Belum Aktif', 'Cicilan'],
            'data' => [$aktif, $belumAktif, $cicilan],
            'colors' => ['#10B981', '#F59E0B', '#EF4444'],
        ];
    }
};
?>

<div>
    <div class="flex sm:flex-row flex-col justify-between items-center mb-6">
        <h5 class="text-xl font-semibold text-heading">Dashboard Membership</h5>
    </div>

    {{-- Filter Tanggal --}}
    <div class="mb-6">
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
    </div>

    {{-- Chart Container --}}
    <div class="bg-neutral-primary-soft border border-default rounded-base shadow-xs p-6 mb-6">
        <canvas id="membershipChart" width="400" height="200"></canvas>
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
                                        return context.parsed.y + ' Membership';
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