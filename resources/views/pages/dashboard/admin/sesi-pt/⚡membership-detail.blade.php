<?php

namespace App\Livewire\Pages\Dashboard\Admin\SesiPt;

use App\Models\Membership;
use App\Models\PtBooking;
use Carbon\Carbon;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;

new #[Layout('layouts::admin')] class extends Component
{
    public Membership $membership;

    public bool $showBookingModal = false;
    public string $bookingDate = '';
    public string $bookingTime = '';
    public string $bookingAttendance = 'attended';
    public string $bookingError = '';

    public function mount(Membership $membership): void
    {
        $this->membership = $membership;
    }

    #[Computed]
    public function ptBookings()
    {
        return $this->membership->ptBookings()
            ->with(['member', 'pt'])
            ->orderBy('booking_date', 'desc')
            ->orderBy('booking_time', 'desc')
            ->get();
    }

    #[Computed]
    public function totalSesiHangus(): int
    {
        $noshowCount = $this->membership->ptBookings()
            ->where('attendance', 'noshow')
            ->count();

        return ($this->membership->sesi_hangus ?? 0) + $noshowCount;
    }

    #[Computed]
    public function totalSesiBerjalan(): int
    {
        return $this->membership->ptBookings()
            ->where('attendance', 'attended')
            ->count();
    }

    public function openCreateBookingModal(): void
    {
        if ($this->membership->remaining_sessions <= 0) {
            $this->bookingError = 'Tidak bisa menambah booking. Sisa sesi membership sudah habis.';
            return;
        }

        $this->resetBookingForm();
        $this->showBookingModal = true;
    }

    public function closeBookingModal(): void
    {
        $this->showBookingModal = false;
        $this->resetBookingForm();
        $this->bookingError = '';
    }

    public function resetBookingForm(): void
    {
        $this->bookingDate = '';
        $this->bookingTime = '';
        $this->bookingAttendance = 'attended';
        $this->bookingError = '';
        $this->resetErrorBag();
    }

    public function saveBooking(): void
    {
        $validated = $this->validate([
            'bookingDate' => 'required|date',
            'bookingTime' => 'required',
            'bookingAttendance' => 'required|in:attended,noshow',
        ], [
            'bookingDate.required' => 'Tanggal booking wajib diisi.',
            'bookingTime.required' => 'Waktu booking wajib diisi.',
            'bookingAttendance.required' => 'Absensi wajib dipilih.',
        ]);

        if ($this->membership->remaining_sessions <= 0) {
            $this->addError('booking', 'Sisa sesi membership sudah habis.');
            return;
        }

        $booking = PtBooking::create([
            'membership_id' => $this->membership->id,
            'member_id' => $this->membership->user_id,
            'pt_id' => $this->membership->pt_id,
            'booking_date' => $validated['bookingDate'],
            'booking_time' => $validated['bookingTime'],
            'status' => 'approved',
            'attendance' => $validated['bookingAttendance'],
        ]);

        $membership = $booking->membership;
        if ($membership && $membership->remaining_sessions > 0) {
            $membership->decrement('remaining_sessions');
            if ($membership->remaining_sessions == 0) {
                $membership->update(['status' => 'completed']);
            }
        }

        $this->membership->refresh();
        session()->flash('success', 'Booking berhasil ditambahkan.');
        $this->closeBookingModal();
    }

    public function deleteBooking(int $id): void
    {
        $booking = PtBooking::findOrFail($id);
        $membership = $booking->membership;

        if (in_array($booking->attendance, ['attended', 'noshow']) && $membership) {
            $membership->increment('remaining_sessions');
            if ($membership->status === 'completed') {
                $membership->update(['status' => 'active']);
            }
        }

        $booking->delete();
        $this->membership->refresh();

        session()->flash('success', 'Booking berhasil dihapus.');
    }


};
?>

<div>
    <div class="mb-6">
        <a href="{{ route('admin.sesi-pt.detail', $membership->pt_id) }}" wire:navigate class="inline-flex items-center text-sm font-medium text-body hover:text-heading transition-colors">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
            </svg>
            Kembali ke Detail Sesi PT
        </a>
    </div>

    <div class="mb-6">
        <h5 class="text-xl font-semibold text-heading">Detail Booking Membership: {{ $membership->user->name ?? '-' }}</h5>
        <p class="text-sm text-body mt-1">Paket: {{ $membership->ptPackage->name ?? '-' }}</p>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
        <div class="bg-neutral-primary-soft shadow-xs rounded-md border border-default p-4">
            <p class="text-xs text-body font-medium uppercase tracking-wide">Sesi Awal</p>
            <p class="text-2xl font-bold text-heading mt-1">{{ $membership->total_sessions ?? 0 }}</p>
        </div>
        <div class="bg-neutral-primary-soft shadow-xs rounded-md border border-default p-4">
            <p class="text-xs text-body font-medium uppercase tracking-wide">Sesi Ditambahkan</p>
            <p class="text-2xl font-bold text-heading mt-1">{{ $membership->sesi_ditambahkan ?? 0 }}</p>
        </div>
        <div class="bg-neutral-primary-soft shadow-xs rounded-md border border-default p-4">
            <p class="text-xs text-body font-medium uppercase tracking-wide">Sesi Hangus</p>
            <p class="text-2xl font-bold text-heading mt-1">{{ $this->totalSesiHangus }}</p>
        </div>
        <div class="bg-neutral-primary-soft shadow-xs rounded-md border border-default p-4">
            <p class="text-xs text-body font-medium uppercase tracking-wide">Sesi Berjalan</p>
            <p class="text-2xl font-bold text-heading mt-1">{{ $this->totalSesiBerjalan }}</p>
        </div>
        <div class="bg-neutral-primary-soft shadow-xs rounded-md border border-default p-4">
            <p class="text-xs text-body font-medium uppercase tracking-wide">Sisa Sesi</p>
            <p class="text-2xl font-bold text-heading mt-1">{{ $membership->remaining_sessions ?? 0 }}</p>
        </div>
    </div>

    @if (session()->has('success'))
        <div x-data="{ show: true }" x-show="show" x-transition.duration.300ms class="mb-6 flex items-center justify-between p-4 text-sm text-emerald-800 border border-emerald-200 rounded-md bg-emerald-50 shadow-xs">
            <div class="flex items-center gap-2">
                <svg class="w-5 h-5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                <span class="font-medium">{{ session('success') }}</span>
            </div>
            <button @click="show = false" type="button" class="text-emerald-600 hover:text-emerald-900 focus:outline-none">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
    @endif

    @if ($bookingError)
        <div x-data="{ show: true }" x-show="show" x-transition.duration.300ms class="mb-6 flex items-center justify-between p-4 text-sm text-red-800 border border-red-200 rounded-md bg-red-50 shadow-xs">
            <div class="flex items-center gap-2">
                <svg class="w-5 h-5 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                <span class="font-medium">{{ $bookingError }}</span>
            </div>
            <button @click="show = false" type="button" class="text-red-600 hover:text-red-900 focus:outline-none" wire:click="$set('bookingError', '')">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
    @endif

    <div class="bg-neutral-primary-soft shadow-xs rounded-md border border-default">
        <div class="p-4 flex items-center justify-between">
            <h6 class="text-lg font-semibold text-heading">Jadwal Booking PT</h6>
            <button type="button" wire:click="openCreateBookingModal" class="text-white bg-brand box-border border border-transparent hover:bg-brand-strong focus:ring-4 focus:ring-brand-medium shadow-xs font-medium leading-5 rounded-md text-sm px-4 py-2.5 focus:outline-none">
                + Tambah Booking
            </button>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left rtl:text-right text-body">
                <thead class="text-sm text-body bg-neutral-secondary-medium border-b border-default-medium">
                    <tr>
                        <th scope="col" class="px-6 py-3 font-medium">No</th>
                        <th scope="col" class="px-6 py-3 font-medium">Tanggal Booking</th>
                        <th scope="col" class="px-6 py-3 font-medium">Waktu Booking</th>
                        <th scope="col" class="px-6 py-3 font-medium">Member</th>
                        <th scope="col" class="px-6 py-3 font-medium">PT</th>
                        <th scope="col" class="px-6 py-3 font-medium text-center">Status</th>
                        <th scope="col" class="px-6 py-3 font-medium text-center">Absensi</th>
                        <th scope="col" class="px-6 py-3 font-medium text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->ptBookings as $booking)
                        <tr wire:key="pt-booking-{{ $booking->id }}" class="bg-neutral-primary-soft border-b border-default hover:bg-neutral-secondary-medium">
                            <td class="px-6 py-4 font-medium text-heading whitespace-nowrap">
                                {{ $loop->iteration }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                {{ $booking->booking_date->locale('id')->isoFormat('dddd, D MMM YYYY') }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                {{ $booking->booking_time->format('H:i') }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                {{ $booking->member?->name ?? '-' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                {{ $booking->pt?->name ?? '-' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
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
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
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
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <button type="button" wire:click="deleteBooking({{ $booking->id }})" wire:confirm="Hapus booking ini? Data tidak bisa dikembalikan."
                                    class="inline-flex items-center px-2 py-1 text-xs font-medium text-white bg-red-600 rounded hover:bg-red-700 transition-colors">
                                    Hapus
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-6 py-8 text-center text-gray-500">
                                Belum ada data booking untuk membership ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($showBookingModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm" wire:click.self="closeBookingModal">
            <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4">
                <div class="p-6 border-b border-default-medium flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-heading">Tambah Booking</h3>
                    <button type="button" wire:click="closeBookingModal" class="text-body hover:text-heading">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>

                <div class="p-6 space-y-4">
                    @if ($errors->has('booking'))
                        <div class="p-3 bg-red-100 border border-red-400 text-red-700 rounded text-sm">
                            {{ $errors->first('booking') }}
                        </div>
                    @endif

                    <div>
                        <label for="bookingDate" class="block text-sm font-medium text-heading">Tanggal Booking</label>
                        <input type="date" wire:model="bookingDate" id="bookingDate" class="mt-1 block w-full rounded-md border-default-medium shadow-sm focus:border-brand focus:ring-brand sm:text-sm bg-neutral-secondary-medium px-3 py-2">
                        @error('bookingDate') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label for="bookingTime" class="block text-sm font-medium text-heading">Waktu Booking</label>
                        <select wire:model="bookingTime" id="bookingTime" class="mt-1 block w-full rounded-md border-default-medium shadow-sm focus:border-brand focus:ring-brand sm:text-sm bg-neutral-secondary-medium px-3 py-2">
                            <option value="">Pilih Waktu</option>
                            @for ($h = 7; $h <= 22; $h++)
                                @php $time = str_pad($h, 2, '0', STR_PAD_LEFT) . ':00'; @endphp
                                <option value="{{ $time }}">{{ $time }}</option>
                            @endfor
                        </select>
                        @error('bookingTime') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label for="bookingAttendance" class="block text-sm font-medium text-heading">Absensi</label>
                        <select wire:model="bookingAttendance" id="bookingAttendance" class="mt-1 block w-full rounded-md border-default-medium shadow-sm focus:border-brand focus:ring-brand sm:text-sm bg-neutral-secondary-medium px-3 py-2">
                            <option value="attended">Hadir</option>
                            <option value="noshow">Hangus</option>
                        </select>
                        @error('bookingAttendance') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="p-6 border-t border-default-medium flex gap-3 justify-end">
                    <button type="button" wire:click="closeBookingModal"
                        class="px-4 py-2 text-heading bg-neutral-secondary-medium border border-default-medium rounded-md hover:bg-neutral-secondary-strong font-medium text-sm">
                        Batal
                    </button>
                    <button type="button" wire:click="saveBooking"
                        class="px-4 py-2 text-white bg-brand hover:bg-brand-strong rounded-md font-medium text-sm">
                        Simpan
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
