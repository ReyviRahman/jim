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

<div class="max-w-7xl mx-auto p-4 sm:p-6 lg:p-8">
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h2 class="text-2xl font-bold text-gray-800">Booking Jadwal PT</h2>
            <p class="text-sm text-gray-500 mt-1">Daftar booking sesi per pertemuan dari member Anda.</p>
        </div>

        <div class="flex flex-col sm:flex-row gap-3 w-full sm:w-auto">
            <select wire:model.live="statusFilter"
                class="block w-full sm:w-40 px-3 py-2.5 bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 shadow-sm">
                <option value="">Semua Status</option>
                <option value="pending">Pending</option>
                <option value="approved">Approved</option>
                <option value="cancelled">Cancelled</option>
                <option value="rejected">Rejected</option>
            </select>
            <div class="relative w-full sm:w-72">
                <div class="absolute inset-y-0 start-0 flex items-center ps-3 pointer-events-none">
                    <svg class="w-4 h-4 text-gray-400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 20 20">
                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m19 19-4-4m0-7A7 7 0 1 1 1 8a7 7 0 0 1 14 0Z"/>
                    </svg>
                </div>
                <input
                    type="text"
                    wire:model.live.debounce.300ms="search"
                    class="block w-full ps-10 pe-3 py-2.5 bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 shadow-sm placeholder:text-gray-400"
                    placeholder="Cari nama member...">
            </div>
        </div>
    </div>

    <div class="mb-6 flex flex-col md:flex-row items-center justify-between gap-4 bg-white rounded-xl shadow-sm border border-gray-100 p-4">
        <div class="flex flex-wrap items-center gap-2">
            <button wire:click="previousWeek" class="inline-flex items-center gap-1 px-3 py-2 text-sm font-medium text-gray-700 bg-gray-100 border border-gray-300 rounded-lg hover:bg-gray-200 transition-colors">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
                Minggu Lalu
            </button>
            <button wire:click="thisWeek" class="inline-flex items-center gap-1 px-3 py-2 text-sm font-medium text-white bg-blue-600 border border-blue-600 rounded-lg hover:bg-blue-700 transition-colors">
                Minggu Ini
            </button>
            <button wire:click="nextWeek" class="inline-flex items-center gap-1 px-3 py-2 text-sm font-medium text-gray-700 bg-gray-100 border border-gray-300 rounded-lg hover:bg-gray-200 transition-colors">
                Minggu Depan
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
            </button>
            <input type="date" wire:model.live.debounce.300ms="dateFrom"
                class="px-3 py-2 text-sm font-medium text-gray-900 bg-white border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500 shadow-sm"
                title="Pilih tanggal untuk loncat ke minggu tersebut">
        </div>
        <span class="text-sm font-medium text-gray-700">
            {{ $this->getWeekStart()->locale('id')->isoFormat('D MMM YYYY') }} - {{ $this->getWeekStart()->copy()->addDays(6)->locale('id')->isoFormat('D MMM YYYY') }}
        </span>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-7 gap-4">
        @php
            $dayLabels = $this->daysOfWeek();
        @endphp
        @foreach($dayLabels as $dayKey => $dayName)
            @php
                $currentDayDate = $this->getWeekStart()->copy()->addDays($loop->index);
                $dayBookings = $this->getBookingsForDay($currentDayDate);
                $isToday = $currentDayDate->isToday();
            @endphp
            <div class="bg-white rounded-xl shadow-sm border {{ $isToday ? 'border-blue-400 ring-1 ring-blue-400' : 'border-gray-100' }} overflow-hidden flex flex-col">
                <div class="px-4 py-3 border-b border-gray-100 {{ $isToday ? 'bg-blue-50' : 'bg-gray-50' }}">
                    <h3 class="text-sm font-bold {{ $isToday ? 'text-blue-800' : 'text-gray-700' }}">{{ $dayName }}</h3>
                    <p class="text-xs {{ $isToday ? 'text-blue-600' : 'text-gray-500' }} mt-0.5">
                        {{ $currentDayDate->locale('id')->isoFormat('D MMM') }}
                    </p>
                </div>
                <div class="p-3 flex-1 flex flex-col gap-2 min-h-[120px]">
                    @forelse($dayBookings as $booking)
                        <div class="p-3 rounded-lg border text-sm transition-colors
                            @if($booking->status === 'cancelled') bg-gray-50 border-gray-200 opacity-60
                            @elseif($booking->isRejected()) bg-red-50 border-red-200
                            @elseif($booking->isPending()) bg-orange-50 border-orange-200
                            @else bg-green-50 border-green-200
                            @endif">
                            <div class="font-semibold text-gray-800 truncate">{{ $booking->member?->name ?? '-' }}</div>
                            <div class="text-xs text-gray-600 truncate">{{ $booking->membership?->ptPackage?->name ?? '-' }}</div>
                            <div class="mt-1.5 flex items-center gap-1 text-xs font-medium text-gray-700">
                                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                {{ $booking->booking_time->format('H:i') }}
                            </div>
                            <div class="mt-2 flex flex-wrap items-center gap-1">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium capitalize
                                    @if($booking->status === 'pending') bg-orange-100 text-orange-800
                                    @elseif($booking->status === 'approved') bg-green-100 text-green-800
                                    @elseif($booking->status === 'cancelled') bg-gray-100 text-gray-600
                                    @elseif($booking->status === 'rejected') bg-red-100 text-red-800
                                    @else bg-gray-100 text-gray-800
                                    @endif">
                                    {{ $booking->status }}
                                </span>
                                @if($booking->status === 'approved')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium capitalize
                                        @if($booking->attendance === 'attended') bg-green-100 text-green-800
                                        @elseif($booking->attendance === 'noshow') bg-red-100 text-red-800
                                        @else bg-gray-100 text-gray-600
                                        @endif">
                                        @if($booking->attendance === 'attended') Hadir
                                        @elseif($booking->attendance === 'noshow') Hangus
                                        @else Belum Absen
                                        @endif
                                    </span>
                                @endif
                            </div>

                            @if($booking->status === 'cancelled' && $booking->cancellation_reason)
                                <div class="mt-2 text-[10px] text-gray-500">
                                    <span class="font-medium">Alasan:</span> <span class="italic">"{{ $booking->cancellation_reason }}"</span>
                                </div>
                            @endif
                        </div>
                    @empty
                        <div class="flex-1 flex flex-col items-center justify-center text-center py-4">
                            <svg class="w-8 h-8 text-gray-200 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                            <p class="text-xs text-gray-400">Tidak ada booking</p>
                        </div>
                    @endforelse
                </div>
            </div>
        @endforeach
    </div>

</div>
