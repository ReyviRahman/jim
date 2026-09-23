<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use App\Models\PtBooking;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

new #[Layout('layouts::pt')] class extends Component
{
    public $search = '';
    public $statusFilter = '';

    public $dateFrom = '';
    public $dateTo = '';

    public function mount()
    {
        $this->thisWeek();
    }

    public function updatingSearch()
    {
        //
    }

    public function updatingStatusFilter()
    {
        //
    }

    public function getWeekStart(): Carbon
    {
        if (! empty($this->dateFrom)) {
            return Carbon::parse($this->dateFrom)->startOfWeek(Carbon::MONDAY);
        }

        return now()->startOfWeek(Carbon::MONDAY);
    }

    public function previousWeek()
    {
        $start = $this->getWeekStart()->subWeek();
        $this->dateFrom = $start->format('Y-m-d');
        $this->dateTo = $start->copy()->addDays(6)->format('Y-m-d');
    }

    public function nextWeek()
    {
        $start = $this->getWeekStart()->addWeek();
        $this->dateFrom = $start->format('Y-m-d');
        $this->dateTo = $start->copy()->addDays(6)->format('Y-m-d');
    }

    public function thisWeek()
    {
        $start = now()->startOfWeek(Carbon::MONDAY);
        $this->dateFrom = $start->format('Y-m-d');
        $this->dateTo = $start->copy()->addDays(6)->format('Y-m-d');
    }

    public function daysOfWeek(): array
    {
        return [
            'senin' => 'Senin',
            'selasa' => 'Selasa',
            'rabu' => 'Rabu',
            'kamis' => 'Kamis',
            'jumat' => 'Jumat',
            'sabtu' => 'Sabtu',
            'minggu' => 'Minggu',
        ];
    }

    public function getBookingsForDay(Carbon $date)
    {
        return $this->bookings->filter(function ($booking) use ($date) {
            return $booking->booking_date->format('Y-m-d') === $date->format('Y-m-d');
        })->sortBy('booking_time');
    }

    #[Computed]
    public function bookings()
    {
        $weekStart = $this->getWeekStart();
        $weekEnd = $weekStart->copy()->addDays(6);

        $query = PtBooking::with(['member', 'membership.ptPackage'])
            ->where('pt_id', Auth::id())
            ->whereBetween('booking_date', [$weekStart->format('Y-m-d'), $weekEnd->format('Y-m-d')])
            ->orderBy('booking_date')
            ->orderBy('booking_time');

        if (! empty($this->search)) {
            $query->whereHas('member', function ($q) {
                $q->where('name', 'like', '%'.$this->search.'%');
            });
        }

        if (! empty($this->statusFilter)) {
            $query->where('status', $this->statusFilter);
        }

        return $query->get();
    }
};
?>

<main class="member-pt-schedule mx-auto w-full max-w-[1200px] px-4 pb-10 sm:px-6">
    <header class="relative isolate -mx-4 flex min-h-56 flex-col justify-end overflow-hidden px-4 pb-8 pt-12 sm:-mx-6 sm:rounded-t-3xl sm:px-6 sm:pt-16">
        <img src="{{ asset('member-pt-studio-wide.png') }}" alt="" aria-hidden="true" class="absolute inset-0 -z-20 size-full object-contain">
        <h1 class="text-3xl font-extrabold tracking-tight text-white sm:text-4xl">Jadwal Booking</h1>
        <p class="mt-2 text-lg text-gray-300">Personal Training</p>
    </header>

    <section class="mb-5 rounded-2xl border border-white/15 bg-[#121719] p-4 sm:p-5" aria-label="Filter booking member">
        <h2 class="text-sm font-semibold text-white">Booking member Anda</h2>
        <p class="mt-2 mb-4 text-sm text-gray-400">Daftar booking sesi per pertemuan dari member Anda.</p>
        <div class="flex flex-col gap-3 sm:flex-row">
            <select aria-label="Filter status booking" wire:model.live="statusFilter"
                class="pt-schedule-control w-full sm:w-48">
                <option value="">Semua Status</option>
                <option value="pending">Pending</option>
                <option value="approved">Approved</option>
                <option value="cancelled">Cancelled</option>
                <option value="rejected">Rejected</option>
            </select>
            <div class="relative min-w-0 flex-1">
                <div class="absolute inset-y-0 start-0 flex items-center ps-3 pointer-events-none">
                    <svg class="w-4 h-4 text-gray-400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 20 20">
                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m19 19-4-4m0-7A7 7 0 1 1 1 8a7 7 0 0 1 14 0Z"/>
                    </svg>
                </div>
                <input
                    type="text"
                    aria-label="Cari nama member"
                    wire:model.live.debounce.300ms="search"
                    class="pt-schedule-control w-full ps-10 placeholder:text-gray-400"
                    placeholder="Cari nama member...">
            </div>
        </div>
    </section>

    <nav class="space-y-3" aria-label="Navigasi jadwal">
        <div class="grid grid-cols-[auto_minmax(0,1fr)_auto] gap-2 sm:flex sm:flex-wrap">
            <button type="button" wire:click="previousWeek" class="pt-schedule-control" aria-label="Minggu Lalu"><x-member-package-icon name="chevron" class="size-5 rotate-180"/></button>
            <button type="button" wire:click="thisWeek" class="pt-schedule-control">Minggu Ini</button>
            <button type="button" wire:click="nextWeek" class="pt-schedule-control" aria-label="Minggu Depan"><x-member-package-icon name="chevron" class="size-5"/></button>
            <input type="date" wire:model.live.debounce.300ms="dateFrom" class="pt-schedule-control col-span-3 w-full min-w-0 sm:ml-auto sm:w-auto" aria-label="Pilih tanggal jadwal">
        </div>
        <p class="py-3 text-center text-base font-semibold text-white sm:text-xl">
            {{ $this->getWeekStart()->locale('id')->isoFormat('D MMM YYYY') }} - {{ $this->getWeekStart()->copy()->addDays(6)->locale('id')->isoFormat('D MMM YYYY') }}
        </p>
    </nav>

    <div class="grid items-start gap-4 lg:grid-cols-2">
        @php
            $dayLabels = $this->daysOfWeek();
        @endphp
        @foreach($dayLabels as $dayKey => $dayName)
            @php
                $currentDayDate = $this->getWeekStart()->copy()->addDays($loop->index);
                $dayBookings = $this->getBookingsForDay($currentDayDate);
                $isToday = $currentDayDate->isToday();
            @endphp
            <article wire:key="day-{{ $currentDayDate->toDateString() }}" class="relative overflow-hidden rounded-2xl border p-4 sm:p-5 {{ $isToday ? 'border-brand bg-[#202014]' : 'border-white/15 bg-[#121719]' }}">
                @if($isToday)<span class="absolute inset-y-0 left-0 w-1.5 bg-brand" aria-hidden="true"></span>@endif
                <header class="mb-4 flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-xl font-extrabold sm:text-2xl {{ $isToday ? 'text-brand' : 'text-white' }}">{{ $dayName }}</h2>
                        <p class="mt-1 text-sm text-gray-300">{{ $currentDayDate->locale('id')->isoFormat('D MMM YYYY') }}</p>
                    </div>
                    <span class="shrink-0 rounded-xl bg-white/5 px-3 py-2 text-xs text-gray-300">{{ $dayBookings->count() }} Booking</span>
                </header>
                <div class="grid gap-2 sm:grid-cols-2">
                    @forelse($dayBookings as $booking)
                        <div wire:key="booking-{{ $booking->id }}" class="min-w-0 rounded-xl border border-white/10 bg-black/15 p-3 text-sm">
                            <div class="font-semibold text-white break-words">{{ $booking->member?->name ?? '-' }}</div>
                            <div class="text-xs text-gray-300 break-words">{{ $booking->membership?->ptPackage?->name ?? '-' }}</div>
                            <div class="mt-1.5 flex items-center gap-1 text-xs font-medium text-gray-300">
                                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                {{ $booking->booking_time->format('H:i') }}
                            </div>
                            <div class="mt-2 flex flex-wrap items-center gap-1">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium capitalize
                                    @if($booking->status === 'pending') bg-amber-950 text-amber-200
                                    @elseif($booking->status === 'approved') bg-emerald-950 text-emerald-200
                                    @elseif($booking->status === 'cancelled') bg-slate-800 text-gray-300
                                    @elseif($booking->status === 'rejected') bg-red-950 text-red-200
                                    @else bg-slate-800 text-gray-300
                                    @endif">
                                    {{ $booking->status }}
                                </span>
                                @if($booking->status === 'approved')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium capitalize
                                        @if($booking->attendance === 'attended') bg-emerald-950 text-emerald-200
                                        @elseif($booking->attendance === 'noshow') bg-red-950 text-red-200
                                        @else bg-slate-800 text-gray-300
                                        @endif">
                                        @if($booking->attendance === 'attended') Hadir
                                        @elseif($booking->attendance === 'noshow') Hangus
                                        @else Belum Absen
                                        @endif
                                    </span>
                                @endif
                            </div>

                            @if($booking->status === 'cancelled' && $booking->cancellation_reason)
                                <div class="mt-2 text-[10px] text-gray-400">
                                    <span class="font-medium">Alasan:</span> <span class="italic">"{{ $booking->cancellation_reason }}"</span>
                                </div>
                            @endif
                        </div>
                    @empty
                        <div class="col-span-full flex flex-col items-center justify-center text-center py-4">
                            <svg class="w-8 h-8 text-gray-500 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                            <p class="text-xs text-gray-400">Tidak ada booking</p>
                        </div>
                    @endforelse
                </div>
            </article>
        @endforeach
    </div>

</main>
