<?php

namespace App\Livewire\Pages\Dashboard\Admin\SesiPt;

use App\Models\Membership;
use App\Models\PtBooking;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;

new #[Layout('layouts::admin')] class extends Component
{
    public Membership $membership;

    public bool $showBookingModal = false;
    public bool $showInitialSessionsModal = false;
    public string $initialSessions = '';
    public bool $showRemainingSessionsModal = false;
    public string $remainingSessions = '';
    public string $bookingDate = '';
    public string $bookingTime = '';
    public string $bookingAttendance = 'attended';
    public int $bookingQuantity = 1;
    public string $bookingError = '';
    public string $bookingPtId = '';
    public bool $bookingIsFree = false;

    public array $selectedBookings = [];

    public function mount(Membership $membership): void
    {
        $this->membership = $membership;
    }

    #[Computed]
    public function ptBookings()
    {
        return $this->membership->ptBookings()
            ->with(['member', 'pt', 'membership.members'])
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

    #[Computed]
    public function ptList()
    {
        return User::where('role', 'pt')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    public function openCreateBookingModal(): void
    {
        if ($this->membership->remaining_sessions <= 0 && !$this->bookingIsFree) {
            $this->bookingError = 'Tidak bisa menambah booking. Sisa sesi membership sudah habis.';
            return;
        }

        $this->resetBookingForm();
        $this->showBookingModal = true;
    }

    public function openInitialSessionsModal(): void
    {
        $this->initialSessions = (string) ($this->membership->total_sessions ?? 0);
        $this->resetErrorBag();
        $this->showInitialSessionsModal = true;
    }

    public function closeInitialSessionsModal(): void
    {
        $this->showInitialSessionsModal = false;
        $this->initialSessions = '';
        $this->resetErrorBag();
    }

    public function saveInitialSessions(): void
    {
        $validated = $this->validate([
            'initialSessions' => ['required', 'integer', 'min:0'],
        ], [
            'initialSessions.required' => 'Sesi awal wajib diisi.',
            'initialSessions.integer' => 'Sesi awal harus berupa bilangan bulat.',
            'initialSessions.min' => 'Sesi awal tidak boleh kurang dari 0.',
        ]);

        $newInitialSessions = (int) $validated['initialSessions'];

        $canSave = DB::transaction(function () use ($newInitialSessions): bool {
            $membership = Membership::query()
                ->lockForUpdate()
                ->findOrFail($this->membership->id);

            $initialSessionsDelta = $newInitialSessions - (int) ($membership->total_sessions ?? 0);
            $remainingSessions = (int) ($membership->remaining_sessions ?? 0) + $initialSessionsDelta;

            if ($remainingSessions < 0) {
                return false;
            }

            $membership->update([
                'total_sessions' => $newInitialSessions,
                'remaining_sessions' => $remainingSessions,
            ]);

            return true;
        }, attempts: 3);

        if (! $canSave) {
            $this->addError('initialSessions', 'Sesi awal tidak dapat dikurangi karena sisa sesi tidak boleh menjadi negatif.');

            return;
        }

        $this->membership->refresh();
        $this->closeInitialSessionsModal();
        session()->flash('success', 'Sesi awal berhasil diperbarui.');
    }

    public function openRemainingSessionsModal(): void
    {
        $this->authorizeRemainingSessionsEdit();
        $this->remainingSessions = (string) ($this->membership->remaining_sessions ?? 0);
        $this->resetErrorBag();
        $this->showRemainingSessionsModal = true;
    }

    public function closeRemainingSessionsModal(): void
    {
        $this->showRemainingSessionsModal = false;
        $this->remainingSessions = '';
        $this->resetErrorBag();
    }

    public function saveRemainingSessions(): void
    {
        $this->authorizeRemainingSessionsEdit();
        $this->membership->refresh();
        $totalSessions = (int) ($this->membership->total_sessions ?? 0);
        $validated = $this->validate([
            'remainingSessions' => ['required', 'integer', 'min:0', 'max:'.$totalSessions],
        ], [
            'remainingSessions.required' => 'Sisa sesi wajib diisi.',
            'remainingSessions.integer' => 'Sisa sesi harus berupa bilangan bulat.',
            'remainingSessions.min' => 'Sisa sesi tidak boleh kurang dari 0.',
            'remainingSessions.max' => 'Sisa sesi tidak boleh lebih dari total sesi.',
        ]);

        $newRemainingSessions = (int) $validated['remainingSessions'];

        $canSave = DB::transaction(function () use ($newRemainingSessions): bool {
            $membership = Membership::query()
                ->lockForUpdate()
                ->findOrFail($this->membership->id);

            if ($newRemainingSessions > (int) ($membership->total_sessions ?? 0)) {
                return false;
            }

            $membership->update(['remaining_sessions' => $newRemainingSessions]);

            return true;
        }, attempts: 3);

        if (! $canSave) {
            $this->addError('remainingSessions', 'Sisa sesi tidak boleh lebih dari total sesi.');

            return;
        }

        $this->membership->refresh();
        $this->closeRemainingSessionsModal();
        session()->flash('success', 'Sisa sesi berhasil diperbarui.');
    }

    private function authorizeRemainingSessionsEdit(): void
    {
        abort_unless(
            auth()->check() && in_array(auth()->user()->role, ['admin', 'head_coach'], true),
            403
        );
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
        $this->bookingQuantity = 1;
        $this->bookingError = '';
        $this->bookingPtId = $this->membership->pt_id ?? '';
        $this->bookingIsFree = false;
        $this->resetErrorBag();
    }

    public function saveBooking(): void
    {
        $validated = $this->validate([
            'bookingDate' => 'required|date',
            'bookingTime' => 'required',
            'bookingAttendance' => 'required|in:attended,noshow',
            'bookingQuantity' => 'required|integer|min:1',
            'bookingPtId' => 'required|exists:users,id',
        ], [
            'bookingDate.required' => 'Tanggal booking wajib diisi.',
            'bookingTime.required' => 'Waktu booking wajib diisi.',
            'bookingAttendance.required' => 'Absensi wajib dipilih.',
            'bookingQuantity.required' => 'Jumlah booking wajib diisi.',
            'bookingQuantity.min' => 'Jumlah booking minimal 1.',
            'bookingPtId.required' => 'Coach wajib dipilih.',
            'bookingPtId.exists' => 'Coach tidak valid.',
        ]);

        if (! $this->bookingIsFree && $this->membership->remaining_sessions < $validated['bookingQuantity']) {
            $this->addError('booking', 'Sisa sesi membership tidak mencukupi untuk jumlah booking yang diminta.');
            return;
        }

        for ($i = 0; $i < $validated['bookingQuantity']; $i++) {
            PtBooking::create([
                'membership_id' => $this->membership->id,
                'member_id' => $this->membership->user_id,
                'pt_id' => $validated['bookingPtId'],
                'booking_date' => $validated['bookingDate'],
                'booking_time' => $validated['bookingTime'],
                'status' => 'approved',
                'attendance' => $validated['bookingAttendance'],
                'is_free' => $this->bookingIsFree,
            ]);
        }

        if (! $this->bookingIsFree) {
            $membership = $this->membership->fresh();
            if ($membership && $membership->remaining_sessions > 0) {
                $membership->decrement('remaining_sessions', $validated['bookingQuantity']);
                if ($membership->remaining_sessions <= 0) {
                    $membership->update(['status' => 'completed']);
                }
            }
        }

        $this->membership->refresh();
        $message = $validated['bookingQuantity'] > 1
            ? "{$validated['bookingQuantity']} booking berhasil ditambahkan."
            : 'Booking berhasil ditambahkan.';
        session()->flash('success', $message);
        $this->closeBookingModal();
    }

    public function deleteBooking(int $id): void
    {
        $booking = PtBooking::findOrFail($id);
        $membership = $booking->membership;

        if (! $booking->is_free && in_array($booking->attendance, ['attended', 'noshow']) && $membership) {
            $membership->increment('remaining_sessions');
            if ($membership->status === 'completed') {
                $membership->update(['status' => 'active']);
            }
        }

        $booking->delete();
        $this->membership->refresh();

        session()->flash('success', 'Booking berhasil dihapus.');
    }

    public function bulkAttended(): void
    {
        if (empty($this->selectedBookings)) {
            session()->flash('error', 'Pilih minimal satu booking terlebih dahulu.');
            return;
        }

        $bookings = PtBooking::whereIn('id', $this->selectedBookings)->get();
        $updatedCount = 0;

        foreach ($bookings as $booking) {
            $oldAttendance = $booking->attendance;

            $updateData = ['attendance' => 'attended'];
            if ($booking->status === 'pending') {
                $updateData['status'] = 'approved';
            }

            $booking->update($updateData);

            if (! $booking->is_free && ($oldAttendance === 'not_yet' || $oldAttendance === null)) {
                $this->membership->decrement('remaining_sessions');
            }

            $updatedCount++;
        }

        $this->membership->refresh();
        $this->selectedBookings = [];
        session()->flash('success', "{$updatedCount} booking berhasil ditandai hadir.");
    }

    public function bulkNoshow(): void
    {
        if (empty($this->selectedBookings)) {
            session()->flash('error', 'Pilih minimal satu booking terlebih dahulu.');
            return;
        }

        $bookings = PtBooking::whereIn('id', $this->selectedBookings)->get();
        $updatedCount = 0;

        foreach ($bookings as $booking) {
            $oldAttendance = $booking->attendance;

            $updateData = ['attendance' => 'noshow'];
            if ($booking->status === 'pending') {
                $updateData['status'] = 'approved';
            }

            $booking->update($updateData);

            if (! $booking->is_free && ($oldAttendance === 'not_yet' || $oldAttendance === null)) {
                $this->membership->decrement('remaining_sessions');
            }

            $updatedCount++;
        }

        $this->membership->refresh();
        $this->selectedBookings = [];
        session()->flash('success', "{$updatedCount} booking berhasil ditandai hangus.");
    }

    public function toggleSelectAll(): void
    {
        $selectableIds = $this->ptBookings
            ->filter(fn ($booking) => in_array($booking->status, ['approved', 'pending']))
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->toArray();

        $currentSelected = collect($this->selectedBookings)->map(fn ($id) => (string) $id)->toArray();
        $allSelected = empty(array_diff($selectableIds, $currentSelected));

        $this->selectedBookings = $allSelected ? [] : $selectableIds;
    }

    #[Computed]
    public function isAllBookingsSelected(): bool
    {
        $selectableIds = $this->ptBookings
            ->filter(fn ($booking) => in_array($booking->status, ['approved', 'pending']))
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->toArray();

        $currentSelected = collect($this->selectedBookings)->map(fn ($id) => (string) $id)->toArray();

        return ! empty($selectableIds) && empty(array_diff($selectableIds, $currentSelected));
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
        <h5 class="text-xl font-semibold text-heading">
            Detail Booking Membership: {{ $membership->user->name ?? '-' }}
            @if($membership->members && $membership->members->count() > 1)
                <span class="text-sm font-normal text-body">
                    &
                    @foreach($membership->members->where('id', '!=', $membership->user_id) as $member)
                        {{ $member->name }}@if(!$loop->last), @endif
                    @endforeach
                </span>
            @endif
        </h5>
        <p class="text-sm text-body mt-1">Paket: {{ $membership->ptPackage->name ?? '-' }}</p>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
        <div class="bg-neutral-primary-soft shadow-xs rounded-md border border-default p-4">
            <div class="flex items-center justify-between gap-2">
                <p class="text-xs text-body font-medium uppercase tracking-wide">Sesi Awal</p>
                <button type="button" wire:click="openInitialSessionsModal" class="text-xs font-medium text-brand hover:text-brand-strong">
                    Edit
                </button>
            </div>
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
            <div class="flex items-center justify-between gap-2">
                <p class="text-xs text-body font-medium uppercase tracking-wide">Sisa Sesi</p>
                <button type="button" wire:click="openRemainingSessionsModal" class="text-xs font-medium text-brand hover:text-brand-strong">
                    Edit
                </button>
            </div>
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

    @if (session()->has('error'))
        <div x-data="{ show: true }" x-show="show" x-transition.duration.300ms class="mb-6 flex items-center justify-between p-4 text-sm text-red-800 border border-red-200 rounded-md bg-red-50 shadow-xs">
            <div class="flex items-center gap-2">
                <svg class="w-5 h-5 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <span class="font-medium">{{ session('error') }}</span>
            </div>
            <button @click="show = false" type="button" class="text-red-600 hover:text-red-900 focus:outline-none">
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
            <div class="flex items-center gap-2 flex-wrap">
                @if (count($this->selectedBookings) > 0)
                    <button type="button" wire:click="bulkAttended" class="inline-flex items-center text-white bg-emerald-600 box-border border border-transparent hover:bg-emerald-700 focus:ring-4 focus:ring-emerald-300 shadow-xs font-medium leading-5 rounded-md text-sm px-4 py-2.5 focus:outline-none">
                        Tandai Hadir
                    </button>
                    <button type="button" wire:click="bulkNoshow" class="inline-flex items-center text-white bg-red-600 box-border border border-transparent hover:bg-red-700 focus:ring-4 focus:ring-red-300 shadow-xs font-medium leading-5 rounded-md text-sm px-4 py-2.5 focus:outline-none">
                        Tandai Hangus
                    </button>
                @endif
                <a href="{{ route('admin.sesi-pt.attendance-pdf', $membership->id) }}" target="_blank"
                   class="inline-flex items-center text-white bg-red-600 box-border border border-transparent hover:bg-red-700 focus:ring-4 focus:ring-red-300 shadow-xs font-medium leading-5 rounded-md text-sm px-4 py-2.5 focus:outline-none">
                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    Download PDF
                </a>
                <button type="button" wire:click="openCreateBookingModal" class="text-white bg-brand box-border border border-transparent hover:bg-brand-strong focus:ring-4 focus:ring-brand-medium shadow-xs font-medium leading-5 rounded-md text-sm px-4 py-2.5 focus:outline-none">
                    + Tambah Booking
                </button>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left rtl:text-right text-body">
                <thead class="text-sm text-body bg-neutral-secondary-medium border-b border-default-medium">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-center w-10">
                            <input type="checkbox" wire:click.prevent="toggleSelectAll" @checked($this->isAllBookingsSelected) class="w-4 h-4 text-brand border-default-medium rounded focus:ring-brand">
                        </th>
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
                            <td class="px-6 py-4 text-center whitespace-nowrap">
                                @if(in_array($booking->status, ['approved', 'pending']))
                                    <input type="checkbox" wire:model.live="selectedBookings" value="{{ $booking->id }}" class="w-4 h-4 text-brand border-default-medium rounded focus:ring-brand">
                                @endif
                            </td>
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
                                @if($booking->membership && $booking->membership->members && $booking->membership->members->count() > 1)
                                    <div class="text-xs text-body font-normal mt-0.5">
                                        @foreach($booking->membership->members->where('id', '!=', $booking->member_id) as $member)
                                            {{ $member->name }}@if(!$loop->last), @endif
                                        @endforeach
                                    </div>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                {{ $booking->pt?->name ?? '-' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <div class="flex items-center justify-center gap-1 flex-wrap">
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
                                    @if($booking->is_free)
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-purple-100 text-purple-800">Free</span>
                                    @endif
                                </div>
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
                            <td colspan="9" class="px-6 py-8 text-center text-gray-500">
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

                    <div>
                        <label for="bookingPtId" class="block text-sm font-medium text-heading">Coach</label>
                        <select wire:model="bookingPtId" id="bookingPtId" class="mt-1 block w-full rounded-md border-default-medium shadow-sm focus:border-brand focus:ring-brand sm:text-sm bg-neutral-secondary-medium px-3 py-2">
                            <option value="">Pilih Coach</option>
                            @foreach($this->ptList as $pt)
                                <option value="{{ $pt->id }}">{{ $pt->name }}</option>
                            @endforeach
                        </select>
                        @error('bookingPtId') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                    </div>

                    <div class="flex items-center gap-2">
                        <input type="checkbox" wire:model="bookingIsFree" id="bookingIsFree" class="w-4 h-4 text-brand border-default-medium rounded focus:ring-brand">
                        <label for="bookingIsFree" class="text-sm font-medium text-heading">Sesi Free (tidak memotong sisa sesi)</label>
                    </div>

                    <div>
                        <label for="bookingQuantity" class="block text-sm font-medium text-heading">Jumlah Booking</label>
                        <input type="number" wire:model="bookingQuantity" id="bookingQuantity" min="1" class="mt-1 block w-full rounded-md border-default-medium shadow-sm focus:border-brand focus:ring-brand sm:text-sm bg-neutral-secondary-medium px-3 py-2">
                        @error('bookingQuantity') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
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

    @if ($showInitialSessionsModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm" wire:click.self="closeInitialSessionsModal">
            <div class="w-full max-w-md mx-4 bg-white rounded-lg shadow-xl">
                <div class="flex items-center justify-between p-6 border-b border-default-medium">
                    <h3 class="text-lg font-semibold text-heading">Edit Sesi Awal</h3>
                    <button type="button" wire:click="closeInitialSessionsModal" class="text-body hover:text-heading" aria-label="Tutup modal edit sesi awal">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>

                <div class="p-6">
                    <label for="initialSessions" class="block text-sm font-medium text-heading">Sesi Awal</label>
                    <input type="number" wire:model="initialSessions" id="initialSessions" min="0" class="block w-full px-3 py-2 mt-1 rounded-md border-default-medium shadow-sm focus:border-brand focus:ring-brand sm:text-sm bg-neutral-secondary-medium">
                    @error('initialSessions') <span class="block mt-1 text-xs text-red-500">{{ $message }}</span> @enderror
                </div>

                <div class="flex justify-end gap-3 p-6 border-t border-default-medium">
                    <button type="button" wire:click="closeInitialSessionsModal" class="px-4 py-2 text-sm font-medium rounded-md border border-default-medium text-heading bg-neutral-secondary-medium hover:bg-neutral-secondary-strong">
                        Batal
                    </button>
                    <button type="button" wire:click="saveInitialSessions" class="px-4 py-2 text-sm font-medium text-white rounded-md bg-brand hover:bg-brand-strong">
                        Simpan
                    </button>
                </div>
            </div>
        </div>
    @endif

    @if ($showRemainingSessionsModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm" wire:click.self="closeRemainingSessionsModal">
            <div class="w-full max-w-md mx-4 bg-white rounded-lg shadow-xl">
                <div class="flex items-center justify-between p-6 border-b border-default-medium">
                    <h3 class="text-lg font-semibold text-heading">Edit Sisa Sesi</h3>
                    <button type="button" wire:click="closeRemainingSessionsModal" class="text-body hover:text-heading" aria-label="Tutup modal edit sisa sesi">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>

                <div class="p-6">
                    <label for="remainingSessions" class="block text-sm font-medium text-heading">Sisa Sesi</label>
                    <input type="number" wire:model="remainingSessions" id="remainingSessions" min="0" max="{{ $membership->total_sessions ?? 0 }}" class="block w-full px-3 py-2 mt-1 rounded-md border-default-medium shadow-sm focus:border-brand focus:ring-brand sm:text-sm bg-neutral-secondary-medium">
                    <p class="mt-1 text-xs text-body">Maksimal {{ $membership->total_sessions ?? 0 }} sesi.</p>
                    @error('remainingSessions') <span class="block mt-1 text-xs text-red-500">{{ $message }}</span> @enderror
                </div>

                <div class="flex justify-end gap-3 p-6 border-t border-default-medium">
                    <button type="button" wire:click="closeRemainingSessionsModal" class="px-4 py-2 text-sm font-medium rounded-md border border-default-medium text-heading bg-neutral-secondary-medium hover:bg-neutral-secondary-strong">
                        Batal
                    </button>
                    <button type="button" wire:click="saveRemainingSessions" class="px-4 py-2 text-sm font-medium text-white rounded-md bg-brand hover:bg-brand-strong">
                        Simpan
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
