<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use App\Models\PtBooking;
use Illuminate\Support\Facades\Auth;

new #[Layout('layouts::pt')] class extends Component
{
    use WithPagination;

    public $search = '';
    public $statusFilter = '';

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

        $booking = PtBooking::where('pt_id', Auth::id())
            ->where('id', $this->cancelBookingId)
            ->where('status', 'approved')
            ->first();

        if (! $booking) {
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
        $booking = PtBooking::where('pt_id', Auth::id())
            ->where('id', $bookingId)
            ->where('status', 'approved')
            ->where('attendance', 'not_yet')
            ->first();

        if (! $booking) {
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

    public function with(): array
    {
        $query = PtBooking::with(['member', 'membership.ptPackage'])
            ->where('pt_id', Auth::id())
            ->orderBy('booking_date', 'desc')
            ->orderBy('booking_time', 'desc');

        if (! empty($this->search)) {
            $query->whereHas('member', function ($q) {
                $q->where('name', 'like', '%'.$this->search.'%');
            });
        }

        if (! empty($this->statusFilter)) {
            $query->where('status', $this->statusFilter);
        }

        return [
            'bookings' => $query->paginate(10),
        ];
    }
};
?>

<div class="max-w-6xl mx-auto p-4 sm:p-6 lg:p-8">
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

    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h2 class="text-2xl font-bold text-gray-800">Booking Jadwal PT</h2>
            <p class="text-sm text-gray-500 mt-1">Daftar booking sesi per pertemuan dari member Anda.</p>
        </div>

        <div class="flex flex-col sm:flex-row gap-3 w-full sm:w-auto">
            <select wire:model.live="statusFilter"
                class="block w-full sm:w-40 px-3 py-2.5 bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 shadow-sm">
                <option value="">Semua Status</option>
                <option value="approved">Approved</option>
                <option value="cancelled">Cancelled</option>
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

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left text-gray-600">
                <thead class="text-xs text-gray-700 uppercase bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th scope="col" class="px-6 py-4 font-semibold">No</th>
                        <th scope="col" class="px-6 py-4 font-semibold">Member</th>
                        <th scope="col" class="px-6 py-4 font-semibold">Paket</th>
                        <th scope="col" class="px-6 py-4 font-semibold">Tanggal</th>
                        <th scope="col" class="px-6 py-4 font-semibold">Waktu</th>
                        <th scope="col" class="px-6 py-4 font-semibold">Absensi</th>
                        <th scope="col" class="px-6 py-4 font-semibold">Status</th>
                        <th scope="col" class="px-6 py-4 font-semibold text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($bookings as $booking)
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap font-medium text-gray-500">
                                {{ $loop->iteration + ($bookings->currentPage() - 1) * $bookings->perPage() }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="text-sm font-medium text-gray-800">{{ $booking->member?->name ?? '-' }}</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="text-xs text-gray-600">{{ $booking->membership?->ptPackage?->name ?? '-' }}</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="text-sm text-gray-700">{{ $booking->booking_date->locale('id')->isoFormat('D MMM YYYY') }}</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="text-sm text-gray-700">{{ $booking->booking_time->format('H:i') }}</span>
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
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium capitalize
                                    @if($booking->status === 'approved') bg-green-100 text-green-800
                                    @elseif($booking->status === 'cancelled') bg-gray-100 text-gray-600
                                    @else bg-gray-100 text-gray-800
                                    @endif">
                                    {{ $booking->status }}
                                </span>

                                @if($booking->status === 'cancelled' && $booking->cancellation_reason)
                                    <div class="mt-1 text-xs text-gray-500 max-w-xs">
                                        <span class="font-medium">Alasan:</span> <span class="italic">"{{ $booking->cancellation_reason }}"</span>
                                    </div>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                @if($booking->isApproved() && $booking->isAttendanceNotYet())
                                    <div class="flex items-center justify-center gap-2">
                                        <button type="button" wire:click="markAsNoshow({{ $booking->id }})" wire:confirm="Tandai booking ini sebagai hangus? Sesi akan berkurang."
                                            class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium text-white bg-orange-500 rounded hover:bg-orange-600 transition-colors" title="Jadikan Hangus">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                                            Hangus
                                        </button>
                                        <button type="button" wire:click="openCancelModal({{ $booking->id }})"
                                            class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium text-white bg-red-600 rounded hover:bg-red-700 transition-colors" title="Batalkan">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                            Batal
                                        </button>
                                    </div>
                                @elseif($booking->isApproved() && $booking->isAttended())
                                    <span class="text-xs text-gray-400">Sudah absen</span>
                                @else
                                    <span class="text-xs text-gray-400">-</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-6 py-10 text-center">
                                <svg class="w-12 h-12 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                </svg>
                                <p class="text-gray-500 font-medium">Tidak ada booking ditemukan.</p>
                                @if($search || $statusFilter)
                                    <p class="text-xs text-gray-400 mt-1">Coba ubah filter pencarian Anda.</p>
                                @else
                                    <p class="text-xs text-gray-400 mt-1">Member belum melakukan booking sesi PT.</p>
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($bookings->hasPages())
        <div class="mt-4">
            {{ $bookings->links('components.custom-pagination') }}
        </div>
    @endif

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
