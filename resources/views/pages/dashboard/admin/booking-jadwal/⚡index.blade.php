<?php

namespace App\Livewire\Admin;

use App\Models\Membership;
use App\Models\PtBooking;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::admin')] class extends Component
{
    use WithPagination;

    public $search = '';
    public $statusFilter = '';
    public $ptFilter = '';
    public $dateFrom = '';
    public $dateTo = '';

    public $showCancelModal = false;
    public $cancelBookingId = null;
    public $cancelReason = '';

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingStatusFilter()
    {
        $this->resetPage();
    }

    public function updatingPtFilter()
    {
        $this->resetPage();
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

        $this->resetPage();
    }

    #[Computed]
    public function ptList()
    {
        return User::where('role', 'pt')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function bookings()
    {
        $query = PtBooking::with(['member', 'pt', 'membership.ptPackage', 'cancelledBy'])
            ->orderBy('booking_date', 'desc')
            ->orderBy('booking_time', 'desc');

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

        if (! empty($this->ptFilter)) {
            $query->where('pt_id', $this->ptFilter);
        }

        if (! empty($this->dateFrom)) {
            $query->where('booking_date', '>=', $this->dateFrom);
        }

        if (! empty($this->dateTo)) {
            $query->where('booking_date', '<=', $this->dateTo);
        }

        return $query->paginate(20);
    }

    public function approveCancellation($bookingId)
    {
        $booking = PtBooking::find($bookingId);

        if (! $booking || ! $booking->isCancellationPending()) {
            session()->flash('error', 'Booking tidak ditemukan atau tidak ada request pembatalan.');
            return;
        }

        $booking->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
        ]);

        session()->flash('success', 'Request pembatalan berhasil disetujui. Booking telah dibatalkan.');
    }

    public function rejectCancellation($bookingId)
    {
        $booking = PtBooking::find($bookingId);

        if (! $booking || ! $booking->isCancellationPending()) {
            session()->flash('error', 'Booking tidak ditemukan atau tidak ada request pembatalan.');
            return;
        }

        if ($booking->isAttended()) {
            session()->flash('error', 'Booking sudah diabsen, tidak bisa menolak pembatalan.');
            return;
        }

        $booking->update([
            'cancelled_by' => null,
            'cancellation_reason' => null,
            'cancellation_requested_at' => null,
        ]);

        session()->flash('success', 'Request pembatalan ditolak. Booking kembali ke status approved.');
    }

    public function completeBooking($bookingId)
    {
        $booking = PtBooking::find($bookingId);

        if (! $booking || $booking->status !== 'approved') {
            session()->flash('error', 'Booking tidak ditemukan atau status tidak valid.');
            return;
        }

        $booking->update(['status' => 'completed']);

        $membership = $booking->membership;
        if ($membership && $membership->remaining_sessions > 0) {
            $membership->decrement('remaining_sessions');
        }

        session()->flash('success', 'Booking berhasil diselesaikan. Sisa sesi berkurang.');
    }

    public function openCancelModal($bookingId)
    {
        $this->cancelBookingId = $bookingId;
        $this->cancelReason = '';
        $this->showCancelModal = true;
    }

    public function closeCancelModal()
    {
        $this->showCancelModal = false;
        $this->cancelBookingId = null;
        $this->cancelReason = '';
    }

    public function cancelBooking()
    {
        $this->validate([
            'cancelReason' => 'required|string|min:5|max:500',
        ], [
            'cancelReason.required' => 'Alasan pembatalan wajib diisi.',
            'cancelReason.min' => 'Alasan pembatalan minimal 5 karakter.',
            'cancelReason.max' => 'Alasan pembatalan maksimal 500 karakter.',
        ]);

        $booking = PtBooking::find($this->cancelBookingId);

        if (! $booking || $booking->status !== 'approved') {
            session()->flash('error', 'Booking tidak ditemukan atau status tidak valid.');
            $this->closeCancelModal();
            return;
        }

        $booking->update([
            'status' => 'cancelled',
            'cancelled_by' => Auth::id(),
            'cancelled_at' => now(),
            'cancellation_reason' => $this->cancelReason,
        ]);

        $this->closeCancelModal();
        session()->flash('success', 'Booking berhasil dibatalkan.');
    }

    public function markAsNoshow($bookingId)
    {
        $booking = PtBooking::find($bookingId);

        if (! $booking || $booking->status !== 'approved' || $booking->attendance !== 'not_yet') {
            session()->flash('error', 'Booking tidak valid untuk ditandai hangus.');
            return;
        }

        $membership = $booking->membership;

        $booking->update(['attendance' => 'noshow']);

        if ($membership && $membership->remaining_sessions > 0) {
            $membership->decrement('remaining_sessions');
            if ($membership->remaining_sessions == 0) {
                $membership->update(['status' => 'completed']);
            }
        }

        session()->flash('success', 'Booking ditandai hangus. Sesi PT berkurang.');
    }

    public function restoreNoshow($bookingId)
    {
        $booking = PtBooking::find($bookingId);

        if (! $booking || $booking->attendance !== 'noshow') {
            session()->flash('error', 'Booking tidak valid untuk direstore.');
            return;
        }

        $membership = $booking->membership;

        $booking->update(['attendance' => 'not_yet']);

        if ($membership) {
            $membership->increment('remaining_sessions');
            if ($membership->status === 'completed') {
                $membership->update(['status' => 'active']);
            }
        }

        session()->flash('success', 'Booking berhasil direstore. Sesi PT dikembalikan.');
    }

    public function clearFilters()
    {
        $this->search = '';
        $this->statusFilter = '';
        $this->ptFilter = '';
        $this->dateFrom = '';
        $this->dateTo = '';
        $this->resetPage();
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
        <h5 class="text-xl font-semibold text-heading">Booking Jadwal PT</h5>
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
                    <option value="approved">Approved</option>
                    <option value="completed">Completed</option>
                    <option value="cancelled">Cancelled</option>
                    <option value="pending_cancel">Pending Cancel</option>
                </select>

                <select wire:model.live="ptFilter" class="bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base focus:ring-brand focus:border-brand shadow-xs block w-full md:w-48 ps-3 pe-8 py-2.5">
                    <option value="">Semua PT</option>
                    @foreach($this->ptList as $pt)
                        <option value="{{ $pt->id }}">{{ $pt->name }}</option>
                    @endforeach
                </select>

                <button wire:click="clearFilters" class="inline-flex items-center gap-1 px-3 py-2.5 text-sm font-medium text-body bg-neutral-secondary-medium border border-default-medium rounded hover:bg-neutral-secondary-dark transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M7 12h10M11 18h2"/></svg>
                    Clear
                </button>
            </div>
        </div>

        <div class="p-4 border-t border-default-medium flex flex-col md:flex-row items-center gap-4">
            <span class="text-sm text-body font-medium">Filter Tanggal Sesi:</span>
            <div class="relative w-full md:w-56" wire:ignore>
                <div class="absolute inset-y-0 start-0 flex items-center ps-3 pointer-events-none">
                    <svg class="w-4 h-4 text-body" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 10h16M8 14h8m-4-7V4M7 7V4m10 3V4M5 20h14a1 1 0 0 0 1-1V7a1 1 0 0 0-1-1H5a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1Z"/></svg>
                </div>
                <input type="text" x-data
                    x-init="flatpickr($el, {
                        mode: 'range',
                        dateFormat: 'Y-m-d',
                        placeholder: 'Pilih Tanggal',
                        onClose: function(selectedDates, dateStr, instance) {
                            @this.call('setDateRange', dateStr)
                        }
                    })"
                    class="block w-full ps-9 pe-3 py-2 bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base focus:ring-brand focus:border-brand shadow-xs placeholder:text-body"
                    placeholder="Pilih Rentang Tanggal">
            </div>
        </div>

        <table class="w-full text-sm text-left rtl:text-right text-body">
            <thead class="text-sm text-body bg-neutral-secondary-medium border-b border-default-medium">
                <tr>
                    <th scope="col" class="px-6 py-3 font-medium">No</th>
                    <th scope="col" class="px-6 py-3 font-medium">Member</th>
                    <th scope="col" class="px-6 py-3 font-medium">Coach</th>
                    <th scope="col" class="px-6 py-3 font-medium">Paket</th>
                    <th scope="col" class="px-6 py-3 font-medium">Tanggal</th>
                    <th scope="col" class="px-6 py-3 font-medium">Waktu</th>
                    <th scope="col" class="px-6 py-3 font-medium">Status</th>
                    <th scope="col" class="px-6 py-3 font-medium">Absensi</th>
                    <th scope="col" class="px-6 py-3 font-medium">Info Pembatalan</th>
                    <th scope="col" class="px-6 py-3 font-medium">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($this->bookings as $booking)
                    <tr wire:key="{{ $booking->id }}" class="bg-neutral-primary-soft border-b border-default hover:bg-neutral-secondary-medium">
                        <td class="px-6 py-4 font-medium text-heading whitespace-nowrap">
                            {{ $loop->iteration + ($this->bookings->currentPage() - 1) * $this->bookings->perPage() }}
                        </td>
                        <td class="px-6 py-4 font-medium text-heading whitespace-nowrap">
                            {{ $booking->member?->name ?? '-' }}
                        </td>
                        <td class="px-6 py-4 text-heading whitespace-nowrap">
                            {{ $booking->pt?->name ?? '-' }}
                        </td>
                        <td class="px-6 py-4 text-heading whitespace-nowrap">
                            {{ $booking->membership?->ptPackage?->name ?? '-' }}
                        </td>
                        <td class="px-6 py-4 text-heading whitespace-nowrap">
                            {{ $booking->booking_date->locale('id')->isoFormat('D MMM YYYY') }}
                        </td>
                        <td class="px-6 py-4 text-heading whitespace-nowrap">
                            {{ $booking->booking_time->format('H:i') }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @if($booking->isCancellationPending())
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium capitalize bg-yellow-100 text-yellow-800">
                                    Pending Cancel
                                </span>
                            @else
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium capitalize
                                    @if($booking->status === 'approved') bg-green-100 text-green-800
                                    @elseif($booking->status === 'completed') bg-blue-100 text-blue-800
                                    @elseif($booking->status === 'cancelled') bg-gray-100 text-gray-600
                                    @else bg-gray-100 text-gray-800
                                    @endif">
                                    {{ $booking->status }}
                                </span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
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
                            @else
                                <span class="text-xs text-gray-400">-</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-heading max-w-xs">
                            @if($booking->isCancellationPending())
                                <div class="text-xs">
                                    <span class="text-yellow-600 font-medium">Menunggu Approval</span>
                                    <div class="text-body mt-1">
                                        {{ $booking->cancelledBy?->name ?? '-' }} -
                                        {{ $booking->cancellation_requested_at->locale('id')->isoFormat('D MMM YYYY HH:mm') }}
                                    </div>
                                    @if($booking->cancellation_reason)
                                        <div class="text-red-600 mt-1 italic">"{{ $booking->cancellation_reason }}"</div>
                                    @endif
                                </div>
                            @elseif($booking->status === 'cancelled' && $booking->cancelled_at)
                                <div class="text-xs">
                                    <span class="text-gray-600">Dibatalkan oleh {{ $booking->cancelledBy?->name ?? '-' }}</span>
                                    <div class="text-body">
                                        {{ $booking->cancelled_at->locale('id')->isoFormat('D MMM YYYY HH:mm') }}
                                    </div>
                                    @if($booking->cancellation_reason)
                                        <div class="text-gray-500 mt-1 italic">"{{ $booking->cancellation_reason }}"</div>
                                    @endif
                                </div>
                            @else
                                <span class="text-xs text-gray-400">-</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @if($booking->isCancellationPending())
                                <div class="flex items-center gap-2">
                                    <button wire:click="approveCancellation({{ $booking->id }})" wire:confirm="Setujui request pembatalan ini?"
                                        class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium text-white bg-green-600 rounded hover:bg-green-700 transition-colors">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                        Approve
                                    </button>
                                    @if($booking->isAttendanceNotYet())
                                        <button wire:click="rejectCancellation({{ $booking->id }})" wire:confirm="Tolak request pembatalan ini? Booking akan kembali ke status Approved."
                                            class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium text-white bg-gray-500 rounded hover:bg-gray-600 transition-colors">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                            Reject
                                        </button>
                                    @else
                                        <span class="text-xs text-gray-400">Sudah absen</span>
                                    @endif
                                </div>
                            @elseif($booking->status === 'approved')
                                @if($booking->attendance === 'not_yet')
                                    <div class="flex items-center gap-2">
                                        <button wire:click="openCancelModal({{ $booking->id }})"
                                            class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium text-white bg-red-600 rounded hover:bg-red-700 transition-colors" title="Batalkan Booking">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                            Batal
                                        </button>
                                        <button wire:click="markAsNoshow({{ $booking->id }})" wire:confirm="Tandai booking ini sebagai hangus? Sesi akan berkurang."
                                            class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium text-white bg-orange-500 rounded hover:bg-orange-600 transition-colors" title="Jadikan Hangus">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                                            Hangus
                                        </button>
                                    </div>
                                @elseif($booking->attendance === 'noshow')
                                    <div class="flex items-center gap-2">
                                        <button wire:click="restoreNoshow({{ $booking->id }})" wire:confirm="Restore booking ini? Sesi akan dikembalikan."
                                            class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium text-white bg-blue-500 rounded hover:bg-blue-600 transition-colors" title="Restore">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/></svg>
                                        </button>
                                    </div>
                                @else
                                    <span class="text-xs text-gray-400">-</span>
                                @endif
                            @else
                                <span class="text-xs text-gray-400">-</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="px-6 py-8 text-center text-gray-500">
                            Tidak ada data booking yang ditemukan.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $this->bookings->links() }}
    </div>

    @if($showCancelModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm" wire:click.self="closeCancelModal">
            <div class="bg-white rounded-lg shadow-xl w-full max-w-md mx-4 p-6" @click.stop>
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Alasan Pembatalan</h3>
                    <button type="button" wire:click="closeCancelModal" class="text-gray-400 hover:text-gray-600">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>

                @if($errors->any())
                    <div class="mb-4 p-3 bg-red-100 border border-red-400 text-red-700 rounded text-sm">
                        <ul class="list-disc list-inside">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form wire:submit.prevent="cancelBooking" class="space-y-4">
                    <div>
                        <label for="cancelReason" class="block text-sm font-medium text-gray-700 mb-1">
                            Alasan Pembatalan <span class="text-red-500">*</span>
                        </label>
                        <textarea id="cancelReason" wire:model="cancelReason" rows="4"
                            class="block w-full px-3 py-2 bg-white border border-gray-300 rounded-md text-sm focus:ring-blue-500 focus:border-blue-500"
                            placeholder="Silakan isi alasan mengapa booking ini dibatalkan..."></textarea>
                        @error('cancelReason') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                    </div>

                    <div class="flex gap-3 pt-2">
                        <button type="button" wire:click="closeCancelModal"
                            class="flex-1 px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200">
                            Batal
                        </button>
                        <button type="submit" wire:loading.attr="disabled"
                            class="flex-1 px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-md hover:bg-red-700 disabled:opacity-50">
                            <span wire:loading.remove wire:target="cancelBooking">Batalkan Booking</span>
                            <span wire:loading wire:target="cancelBooking">Menyimpan...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>