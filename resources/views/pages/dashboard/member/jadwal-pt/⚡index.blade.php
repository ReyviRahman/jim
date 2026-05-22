<?php

use App\Models\PtBooking;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::member')] class extends Component
{
    public $search = '';
    public $statusFilter = '';
    public $dateFrom = '';
    public $dateTo = '';

    public $showDetailModal = false;
    public $selectedBookingId = null;

    public function mount()
    {
        $this->thisWeek();
    }

    public function updatingSearch()
    {
        // no pagination to reset
    }

    public function updatingStatusFilter()
    {
        // no pagination to reset
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

    public function setDateRange($rangeStr)
    {
        if (str_contains($rangeStr, ' to ')) {
            $dates = explode(' to ', $rangeStr);
            $this->dateFrom = $dates[0];
            $this->dateTo = $dates[1];
        } elseif ($rangeStr) {
            $this->dateFrom = $rangeStr;
            $this->dateTo = $rangeStr;
        } else {
            $this->dateFrom = '';
            $this->dateTo = '';
        }
    }

    #[Computed]
    public function bookings()
    {
        $weekStart = $this->getWeekStart();
        $weekEnd = $weekStart->copy()->addDays(6);
        $userId = Auth::id();

        $query = PtBooking::with(['member', 'pt', 'membership.ptPackage', 'membership.members', 'cancelledBy'])
            ->whereBetween('booking_date', [$weekStart->format('Y-m-d'), $weekEnd->format('Y-m-d')])
            ->whereHas('membership', function ($q) use ($userId) {
                $q->where('type', 'pt')
                    ->where(function ($subQ) use ($userId) {
                        $subQ->where('user_id', $userId)
                            ->orWhereHas('members', function ($memberQ) use ($userId) {
                                $memberQ->where('users.id', $userId);
                            });
                    });
            })
            ->orderBy('booking_date')
            ->orderBy('booking_time');

        if (! empty($this->search)) {
            $query->whereHas('member', function ($q) {
                $q->where('name', 'like', '%'.$this->search.'%');
            });
        }

        if (! empty($this->statusFilter)) {
            if ($this->statusFilter === 'pending_cancel') {
                $query->whereNotNull('cancellation_requested_at')
                    ->where('status', 'approved');
            } else {
                $query->where('status', $this->statusFilter);
            }
        }

        return $query->get();
    }

    public function timeSlots(): array
    {
        $slots = [];
        for ($h = 7; $h <= 22; $h++) {
            $start = str_pad((string) $h, 2, '0', STR_PAD_LEFT).':00';
            $end = str_pad((string) ($h + 1), 2, '0', STR_PAD_LEFT).':00';
            $slots[] = ['hour' => $h, 'label' => $start.' - '.$end];
        }

        return $slots;
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

    public function getBookingsForSlot(string $day, int $hour)
    {
        return $this->bookings->filter(function ($booking) use ($day, $hour) {
            $bookingDay = strtolower($booking->booking_date->locale('id')->isoFormat('dddd'));
            $bookingHour = (int) $booking->booking_time->format('H');

            return $bookingDay === $day && $bookingHour === $hour;
        });
    }

    #[Computed]
    public function selectedBooking(): ?PtBooking
    {
        if (! $this->selectedBookingId) {
            return null;
        }

        return PtBooking::with(['member', 'pt', 'membership.ptPackage', 'membership.members', 'cancelledBy'])->find($this->selectedBookingId);
    }

    public function openDetailModal($bookingId)
    {
        $this->selectedBookingId = $bookingId;
        $this->showDetailModal = true;
    }

    public function closeDetailModal()
    {
        $this->showDetailModal = false;
        $this->selectedBookingId = null;
    }

    public function clearFilters()
    {
        $this->search = '';
        $this->statusFilter = '';
        $this->thisWeek();
    }
}; ?>

<div>
    @if (session()->has('success'))
        <div class="mb-4 p-3 bg-green-100 border border-green-400 text-green-700 rounded text-sm">
            {{ session('success') }}
        </div>
    @endif
    @if (session()->has('error'))
        <div class="mb-4 p-3 bg-red-100 border border-red-400 text-red-700 rounded text-sm">
            {{ session('error') }}
        </div>
    @endif

    <div class="flex sm:flex-row flex-col justify-between items-center mb-6">
        <h5 class="text-xl font-semibold text-heading">Jadwal PT Saya</h5>
    </div>

    <div class="relative overflow-x-auto bg-neutral-primary-soft shadow-xs rounded-md border border-default">
        <div class="p-4 flex flex-col md:flex-row items-center justify-between gap-4">
            <div class="relative w-full md:w-auto md:flex-1">
                <div class="absolute inset-y-0 start-0 flex items-center ps-3 pointer-events-none">
                    <svg class="w-4 h-4 text-body" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-width="2" d="m21 21-3.5-3.5M17 10a7 7 0 1 1-14 0 7 7 0 0 1 14 0Z"/></svg>
                </div>
                <input type="text" wire:model.live.debounce.300ms="search" class="block w-full max-w-sm ps-9 pe-3 py-2.5 bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base focus:ring-brand focus:border-brand shadow-xs placeholder:text-body" placeholder="Cari nama member...">
            </div>

            <div class="flex items-center gap-3 w-full md:w-auto flex-wrap">
                <select wire:model.live="statusFilter" class="bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base focus:ring-brand focus:border-brand shadow-xs block w-full md:w-40 ps-3 pe-8 py-2.5">
                    <option value="">Semua Status</option>
                    <option value="pending">Pending</option>
                    <option value="approved">Approved</option>
                    <option value="cancelled">Cancelled</option>
                    <option value="rejected">Rejected</option>
                    <option value="pending_cancel">Pending Cancel</option>
                </select>

                <button wire:click="clearFilters" class="inline-flex items-center gap-1 px-3 py-2.5 text-sm font-medium text-body bg-neutral-secondary-medium border border-default-medium rounded hover:bg-neutral-secondary-dark transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M7 12h10M11 18h2"/></svg>
                    Clear
                </button>
            </div>
        </div>

        <div class="p-4 border-t border-default-medium flex flex-col md:flex-row items-center justify-between gap-4">
            <div class="flex items-center gap-2">
                <button wire:click="previousWeek" class="inline-flex items-center gap-1 px-3 py-2 text-sm font-medium text-body bg-neutral-secondary-medium border border-default-medium rounded hover:bg-neutral-secondary-dark transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
                    Minggu Lalu
                </button>
                <button wire:click="thisWeek" class="inline-flex items-center gap-1 px-3 py-2 text-sm font-medium text-white bg-brand border border-brand rounded hover:bg-brand-dark transition-colors">
                    Minggu Ini
                </button>
                <button wire:click="nextWeek" class="inline-flex items-center gap-1 px-3 py-2 text-sm font-medium text-body bg-neutral-secondary-medium border border-default-medium rounded hover:bg-neutral-secondary-dark transition-colors">
                    Minggu Depan
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                </button>
                <input type="date" wire:model.live.debounce.300ms="dateFrom"
                    class="px-3 py-2 text-sm font-medium text-heading bg-neutral-secondary-medium border border-default-medium rounded focus:ring-brand focus:border-brand shadow-xs"
                    title="Pilih tanggal untuk loncat ke minggu tersebut">
            </div>
            <span class="text-sm font-medium text-heading">
                {{ $this->getWeekStart()->locale('id')->isoFormat('D MMM YYYY') }} - {{ $this->getWeekStart()->copy()->addDays(6)->locale('id')->isoFormat('D MMM YYYY') }}
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left text-body border-collapse">
                <thead class="text-sm text-body bg-neutral-secondary-medium border-b border-default-medium">
                    <tr>
                        <th scope="col" class="px-4 py-3 font-medium whitespace-nowrap border-r border-default-medium w-28">Time</th>
                        @php
                            $weekStart = $this->getWeekStart();
                            $dayLabels = $this->daysOfWeek();
                        @endphp
                        @foreach($dayLabels as $dayKey => $dayName)
                            <th scope="col" class="px-4 py-3 font-medium text-center border-r border-default-medium min-w-[140px]">
                                {{ $dayName }}
                                <div class="text-xs font-normal text-body mt-0.5">
                                    {{ $weekStart->copy()->addDays($loop->index)->locale('id')->isoFormat('D MMM') }}
                                </div>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse($this->timeSlots() as $slot)
                        <tr class="bg-neutral-primary-soft border-b border-default hover:bg-neutral-secondary-medium/50">
                            <td class="px-4 py-3 font-medium text-heading whitespace-nowrap border-r border-default-medium bg-neutral-secondary-medium/30">
                                {{ $slot['label'] }}
                            </td>
                            @foreach(array_keys($dayLabels) as $dayKey)
                                @php
                                    $slotBookings = $this->getBookingsForSlot($dayKey, $slot['hour']);
                                @endphp
                                <td class="px-2 py-2 border-r border-default align-top min-w-[140px]">
                                    <div class="flex flex-col gap-1.5">
                                        @foreach($slotBookings as $booking)
                                            <div wire:click="openDetailModal({{ $booking->id }})"
                                                class="cursor-pointer p-2 rounded border text-xs transition-colors
                                                @if($booking->status === 'cancelled') bg-gray-50 border-gray-200 opacity-60
                                                @elseif($booking->isRejected()) bg-red-50 border-red-200
                                                @elseif($booking->isPending()) bg-orange-50 border-orange-200
                                                @elseif($booking->isCancellationPending()) bg-yellow-50 border-yellow-200
                                                @else bg-green-50 border-green-200
                                                @endif">
                                                <div class="font-semibold text-heading truncate">{{ $booking->member?->name ?? '-' }}</div>
                                                @if($booking->membership && $booking->membership->members)
                                                    @foreach($booking->membership->members->where('id', '!=', $booking->member_id) as $member)
                                                        <div class="font-semibold text-heading truncate">{{ $member->name }}</div>
                                                    @endforeach
                                                @endif
                                                <div class="text-body mt-0.5 truncate">{{ $booking->pt?->name ?? '-' }}</div>
                                                <div class="mt-1 flex items-center gap-1">
                                                    @if($booking->isCancellationPending())
                                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-yellow-100 text-yellow-800">Pending Cancel</span>
                                                    @else
                                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium capitalize
                                                            @if($booking->status === 'pending') bg-orange-100 text-orange-800
                                                            @elseif($booking->status === 'approved') bg-green-100 text-green-800
                                                            @elseif($booking->status === 'cancelled') bg-gray-100 text-gray-600
                                                            @elseif($booking->status === 'rejected') bg-red-100 text-red-800
                                                            @else bg-gray-100 text-gray-800
                                                            @endif">
                                                            {{ $booking->status }}
                                                        </span>
                                                    @endif
                                                    @if($booking->status === 'approved')
                                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium capitalize
                                                            @if($booking->attendance === 'attended') bg-green-100 text-green-800
                                                            @elseif($booking->attendance === 'noshow') bg-red-100 text-red-800
                                                            @else bg-gray-100 text-gray-600
                                                            @endif">
                                                            @if($booking->attendance === 'attended') Hadir
                                                            @elseif($booking->attendance === 'noshow') Hangus
                                                            @else Belum
                                                            @endif
                                                        </span>
                                                    @endif
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </td>
                            @endforeach
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-6 py-8 text-center text-gray-500">
                                Tidak ada data slot waktu.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($showDetailModal && $this->selectedBooking)
        @php $booking = $this->selectedBooking; @endphp
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm" wire:click.self="closeDetailModal">
            <div class="bg-white rounded-lg shadow-xl w-full max-w-lg mx-4 max-h-[90vh] overflow-y-auto" @click.stop>
                <div class="flex items-center justify-between p-4 border-b border-gray-200 sticky top-0 bg-white">
                    <h3 class="text-lg font-semibold text-heading">Detail Booking</h3>
                    <button wire:click="closeDetailModal" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <div class="p-4 space-y-4">
                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <span class="text-body">Member</span>
                            <div class="font-medium text-heading">{{ $booking->member?->name ?? '-' }}</div>
                        </div>
                        <div>
                            <span class="text-body">Coach</span>
                            <div class="font-medium text-heading">{{ $booking->pt?->name ?? '-' }}</div>
                        </div>
                        <div>
                            <span class="text-body">Paket</span>
                            <div class="font-medium text-heading">{{ $booking->membership?->ptPackage?->name ?? '-' }}</div>
                        </div>
                        <div>
                            <span class="text-body">Tanggal</span>
                            <div class="font-medium text-heading">{{ $booking->booking_date->locale('id')->isoFormat('dddd, D MMM YYYY') }}</div>
                        </div>
                        <div>
                            <span class="text-body">Waktu</span>
                            <div class="font-medium text-heading">{{ $booking->booking_time->format('H:i') }}</div>
                        </div>
                        <div>
                            <span class="text-body">Status</span>
                            <div class="mt-1">
                                @if($booking->isCancellationPending())
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">Pending Cancel</span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium capitalize
                                        @if($booking->status === 'pending') bg-orange-100 text-orange-800
                                        @elseif($booking->status === 'approved') bg-green-100 text-green-800
                                        @elseif($booking->status === 'cancelled') bg-gray-100 text-gray-600
                                        @elseif($booking->status === 'rejected') bg-red-100 text-red-800
                                        @else bg-gray-100 text-gray-800
                                        @endif">
                                        {{ $booking->status }}
                                    </span>
                                @endif
                            </div>
                        </div>
                        <div>
                            <span class="text-body">Absensi</span>
                            <div class="mt-1">
                                @if($booking->status === 'approved')
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium capitalize
                                        @if($booking->attendance === 'attended') bg-green-100 text-green-800
                                        @elseif($booking->attendance === 'noshow') bg-red-100 text-red-800
                                        @else bg-gray-100 text-gray-600
                                        @endif">
                                        @if($booking->attendance === 'attended') Hadir
                                        @elseif($booking->attendance === 'noshow') Hangus
                                        @else Belum Absen
                                        @endif
                                    </span>
                                @elseif($booking->status === 'pending')
                                    <span class="text-xs text-orange-500">Menunggu Approval</span>
                                @else
                                    <span class="text-xs text-gray-400">-</span>
                                @endif
                            </div>
                        </div>
                    </div>

                    @if($booking->membership && $booking->membership->members && $booking->membership->members->count() > 1)
                        <div class="border-t border-gray-100 pt-3">
                            <span class="text-body text-sm">Member Lain</span>
                            <div class="flex flex-wrap gap-2 mt-1">
                                @foreach($booking->membership->members->where('id', '!=', $booking->member_id) as $member)
                                    <span class="px-2 py-1 bg-neutral-secondary-medium rounded text-xs text-heading">{{ $member->name }}</span>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if($booking->isCancellationPending())
                        <div class="border-t border-gray-100 pt-3">
                            <span class="text-yellow-600 font-medium text-sm">Request Pembatalan</span>
                            <div class="text-xs text-body mt-1">
                                {{ $booking->cancelledBy?->name ?? '-' }} - {{ $booking->cancellation_requested_at->locale('id')->isoFormat('D MMM YYYY HH:mm') }}
                            </div>
                            @if($booking->cancellation_reason)
                                <div class="text-red-600 mt-1 italic text-xs">"{{ $booking->cancellation_reason }}"</div>
                            @endif
                        </div>
                    @elseif($booking->status === 'cancelled' && $booking->cancelled_at)
                        <div class="border-t border-gray-100 pt-3">
                            <span class="text-gray-600 font-medium text-sm">Dibatalkan</span>
                            <div class="text-xs text-body mt-1">
                                {{ $booking->cancelledBy?->name ?? '-' }} - {{ $booking->cancelled_at->locale('id')->isoFormat('D MMM YYYY HH:mm') }}
                            </div>
                            @if($booking->cancellation_reason)
                                <div class="text-gray-500 mt-1 italic text-xs">"{{ $booking->cancellation_reason }}"</div>
                            @endif
                        </div>
                    @elseif($booking->status === 'rejected')
                        <div class="border-t border-gray-100 pt-3">
                            <span class="text-red-600 font-medium text-sm">Booking Ditolak</span>
                            <div class="text-xs text-body mt-1">
                                {{ $booking->rejected_at?->locale('id')->isoFormat('D MMM YYYY HH:mm') ?? '-' }}
                            </div>
                            @if($booking->rejection_reason)
                                <div class="text-red-500 mt-1 italic text-xs">"{{ $booking->rejection_reason }}"</div>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
