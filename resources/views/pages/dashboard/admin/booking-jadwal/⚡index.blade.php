<?php

namespace App\Livewire\Admin;

use App\Models\Membership;
use App\Models\PtBooking;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::admin')] class extends Component
{
    public $search = '';
    public $statusFilter = '';
    public $ptFilter = '';
    public $dateFrom = '';
    public $dateTo = '';

    public $showCancelModal = false;
    public $cancelBookingId = null;
    public $cancelReason = '';

    public $showRejectModal = false;
    public $rejectBookingId = null;
    public $rejectReason = '';

    public $showDetailModal = false;
    public $selectedBookingId = null;

    public $showInsertModal = false;
    public $insertMembershipId = null;
    public $insertMembershipSearch = '';
    public $insertType = 'fleksibel';
    public $insertDate = '';
    public $insertTime = '';
    public $insertPtId = '';
    public $insertIsFree = false;

    public $showChangeCoachModal = false;
    public $changeCoachBookingId = null;
    public $newCoachId = '';
    public $newIsFree = false;

    public function canManageApprovals(): bool
    {
        return in_array(Auth::user()->role, ['admin', 'head_coach']);
    }

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

    public function updatingPtFilter()
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
        $weekStart = $this->getWeekStart();
        $weekEnd = $weekStart->copy()->addDays(6);

        $query = PtBooking::with(['member', 'pt', 'membership.ptPackage', 'membership.members', 'cancelledBy'])
            ->whereBetween('booking_date', [$weekStart->format('Y-m-d'), $weekEnd->format('Y-m-d')])
            ->orderBy('booking_date')
            ->orderBy('booking_time');

        if (! empty($this->search)) {
            $search = '%'.$this->search.'%';
            $query->where(function ($q) use ($search) {
                $q->whereHas('member', function ($sub) use ($search) {
                    $sub->where('name', 'like', $search);
                })
                ->orWhereHas('membership.members', function ($sub) use ($search) {
                    $sub->where('name', 'like', $search);
                });
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

    public function approveCancellation($bookingId)
    {
        if (! $this->canManageApprovals()) {
            session()->flash('error', 'Anda tidak memiliki izin untuk melakukan tindakan ini.');
            return;
        }

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
        if (! $this->canManageApprovals()) {
            session()->flash('error', 'Anda tidak memiliki izin untuk melakukan tindakan ini.');
            return;
        }

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

    public function openCancelModal($bookingId)
    {
        $this->cancelBookingId = $bookingId;
        $this->cancelReason = '';
        $this->showDetailModal = false;
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

    public function openRejectModal($bookingId)
    {
        $this->rejectBookingId = $bookingId;
        $this->rejectReason = '';
        $this->showDetailModal = false;
        $this->showRejectModal = true;
    }

    public function closeRejectModal()
    {
        $this->showRejectModal = false;
        $this->rejectBookingId = null;
        $this->rejectReason = '';
    }

    public function submitRejectBooking()
    {
        if (! $this->canManageApprovals()) {
            session()->flash('error', 'Anda tidak memiliki izin untuk melakukan tindakan ini.');
            $this->closeRejectModal();
            return;
        }

        $this->validate([
            'rejectReason' => 'required|string|min:5|max:500',
        ], [
            'rejectReason.required' => 'Alasan reject wajib diisi.',
            'rejectReason.min' => 'Alasan reject minimal 5 karakter.',
            'rejectReason.max' => 'Alasan reject maksimal 500 karakter.',
        ]);

        $booking = PtBooking::find($this->rejectBookingId);

        if (! $booking || ! $booking->isPending()) {
            session()->flash('error', 'Booking tidak ditemukan atau status tidak valid.');
            $this->closeRejectModal();
            return;
        }

        $booking->update([
            'status' => 'rejected',
            'rejected_at' => now(),
            'rejection_reason' => $this->rejectReason,
        ]);

        $this->closeRejectModal();
        session()->flash('success', 'Booking berhasil di-reject.');
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

    public function markAsAttended(int $bookingId): void
    {
        if (Auth::user()?->role !== 'admin') {
            session()->flash('error', 'Anda tidak memiliki izin untuk melakukan tindakan ini.');

            return;
        }

        $booking = PtBooking::with('membership')->find($bookingId);

        if (! $booking || ! $booking->isApproved() || ! $booking->isAttendanceNotYet()) {
            session()->flash('error', 'Booking tidak valid untuk ditandai hadir.');

            return;
        }

        $booking->update(['attendance' => 'attended']);

        if (! $booking->is_free && $booking->membership?->remaining_sessions > 0) {
            $booking->membership->decrement('remaining_sessions');

            if ($booking->membership->remaining_sessions === 0) {
                $booking->membership->update(['status' => 'completed']);
            }
        }

        session()->flash('success', 'Booking berhasil ditandai hadir.');
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
        $this->thisWeek();
    }

    public function updatedInsertMembershipId()
    {
        $this->resetErrorBag();
    }

    public function openInsertModal($dayKey, $hour)
    {
        $dayOffset = array_search($dayKey, array_keys($this->daysOfWeek()));
        $date = $this->getWeekStart()->copy()->addDays($dayOffset);

        $this->insertDate = $date->format('Y-m-d');
        $this->insertTime = str_pad((string) $hour, 2, '0', STR_PAD_LEFT) . ':00:00';
        $this->insertMembershipId = null;
        $this->insertMembershipSearch = '';
        $this->insertType = 'fleksibel';
        $this->insertPtId = '';
        $this->resetErrorBag();
        $this->showInsertModal = true;
    }

    public function closeInsertModal()
    {
        $this->showInsertModal = false;
        $this->insertMembershipId = null;
        $this->insertMembershipSearch = '';
        $this->insertType = 'fleksibel';
        $this->insertDate = '';
        $this->insertTime = '';
        $this->insertPtId = '';
        $this->insertIsFree = false;
    }

    public function getMembershipSessionNumber(int $membershipId): int
    {
        $countBefore = PtBooking::where('membership_id', $membershipId)
            ->where('is_free', false)
            ->where(function ($query) {
                $query->where('booking_date', '<', $this->insertDate)
                      ->orWhere(function ($q) {
                          $q->where('booking_date', $this->insertDate)
                            ->where('booking_time', '<', $this->insertTime);
                      });
            })
            ->count();

        return $countBefore + 1;
    }

    public function approveBooking($bookingId)
    {
        if (! $this->canManageApprovals()) {
            session()->flash('error', 'Anda tidak memiliki izin untuk melakukan tindakan ini.');
            return;
        }

        $booking = PtBooking::find($bookingId);

        if (! $booking || ! $booking->isPending()) {
            session()->flash('error', 'Booking tidak ditemukan atau status tidak valid.');
            return;
        }

        $booking->update(['status' => 'approved']);
        $this->showDetailModal = false;
        session()->flash('success', 'Booking berhasil di-approve.');
    }

    public function deleteBooking($bookingId)
    {
        $booking = PtBooking::find($bookingId);

        if (! $booking) {
            session()->flash('error', 'Booking tidak ditemukan.');
            return;
        }

        if (Auth::user()->role === 'kasir_gym' && $booking->status === 'approved') {
            session()->flash('error', 'Anda tidak memiliki izin untuk menghapus booking approved.');
            return;
        }

        $booking->delete();
        $this->closeDetailModal();
        session()->flash('success', 'Booking berhasil dihapus.');
    }

    public function selectMembership($id)
    {
        $this->insertMembershipId = $id;
        $this->insertMembershipSearch = '';

        $membership = Membership::find($id);
        $sessionNumber = $this->getMembershipSessionNumber($id);

        if ($membership && $sessionNumber == $membership->remaining_sessions) {
            $this->insertType = 'fleksibel';
        } else {
            $this->insertType = 'keep';
        }

        if ($membership && $membership->pt_id) {
            $this->insertPtId = $membership->pt_id;
        }

        $this->resetErrorBag();
    }

    #[Computed]
    public function filteredMemberships()
    {
        $query = Membership::with(['user', 'ptPackage', 'personalTrainer'])
            ->where('status', 'active')
            ->where('remaining_sessions', '>', 0)
            ->whereNotNull('pt_id')
            ->whereNotNull('pt_package_id');

        if (! empty($this->insertMembershipSearch)) {
            $search = '%'.$this->insertMembershipSearch.'%';
            $query->where(function ($q) use ($search) {
                $q->whereHas('user', fn ($sub) => $sub->where('name', 'like', $search))
                    ->orWhereHas('personalTrainer', fn ($sub) => $sub->where('name', 'like', $search));
            });
        }

        return $query->orderBy('start_date', 'desc')
            ->limit(50)
            ->get();
    }

    public function saveInsertBooking()
    {
        $this->validate([
            'insertMembershipId' => 'required|exists:memberships,id',
            'insertPtId' => 'required|exists:users,id',
        ], [
            'insertMembershipId.required' => 'Pilih membership terlebih dahulu.',
            'insertPtId.required' => 'Pilih coach terlebih dahulu.',
        ]);

        $membership = Membership::find($this->insertMembershipId);

        if (! $membership || $membership->remaining_sessions <= 0) {
            $this->addError('insertBooking', 'Membership tidak valid atau sisa sesi sudah habis.');
            return;
        }

        PtBooking::create([
            'membership_id' => $membership->id,
            'member_id' => $membership->user_id,
            'pt_id' => $this->insertPtId,
            'booking_date' => $this->insertDate,
            'booking_time' => $this->insertTime,
            'status' => Auth::user()->role === 'head_coach' ? 'approved' : 'pending',
            'attendance' => 'not_yet',
            'is_free' => $this->insertIsFree,
        ]);

        $this->closeInsertModal();
        session()->flash('success', 'Booking berhasil ditambahkan.');
    }

    public function openChangeCoachModal($bookingId)
    {
        $booking = PtBooking::find($bookingId);

        if (! $booking) {
            session()->flash('error', 'Booking tidak ditemukan.');
            return;
        }

        $this->changeCoachBookingId = $bookingId;
        $this->newCoachId = $booking->pt_id ?? '';
        $this->newIsFree = $booking->is_free ?? false;
        $this->resetErrorBag();
        $this->showChangeCoachModal = true;
    }

    public function closeChangeCoachModal()
    {
        $this->showChangeCoachModal = false;
        $this->changeCoachBookingId = null;
        $this->newCoachId = '';
        $this->newIsFree = false;
    }

    public function saveChangeCoach()
    {
        $this->validate([
            'newCoachId' => 'required|exists:users,id',
        ], [
            'newCoachId.required' => 'Pilih coach baru terlebih dahulu.',
        ]);

        $booking = PtBooking::find($this->changeCoachBookingId);

        if (! $booking) {
            session()->flash('error', 'Booking tidak ditemukan.');
            $this->closeChangeCoachModal();
            return;
        }

        $oldCoachName = $booking->pt?->name ?? '-';
        $newCoach = User::find($this->newCoachId);

        $booking->update([
            'pt_id' => $this->newCoachId,
            'is_free' => $this->newIsFree,
        ]);

        $this->closeChangeCoachModal();
        session()->flash('success', 'Booking berhasil diperbarui. Coach: '.$oldCoachName.' → '.$newCoach?->name.'.');
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
                {{-- <select wire:model.live="statusFilter" class="bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base focus:ring-brand focus:border-brand shadow-xs block w-full md:w-40 ps-3 pe-8 py-2.5">
                    <option value="">Semua Status</option>
                    <option value="pending">Pending</option>
                    <option value="approved">Approved</option>
                    <option value="cancelled">Cancelled</option>
                    <option value="rejected">Rejected</option>
                    <option value="pending_cancel">Pending Cancel</option>
                </select> --}}

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
                                                    @if($booking->is_free)
                                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-purple-100 text-purple-800">Free</span>
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

                                        <div wire:click="openInsertModal('{{ $dayKey }}', {{ $slot['hour'] }})"
                                            class="cursor-pointer hover:bg-brand/10 rounded flex items-center justify-center transition-colors
                                            @if($slotBookings->isEmpty()) min-h-[60px] bg-brand/5 @else py-1 border border-dashed border-brand/30 @endif">
                                            <svg class="w-5 h-5 text-brand" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
                                        </div>
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

    @if($showRejectModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm" wire:click.self="closeRejectModal">
            <div class="bg-white rounded-lg shadow-xl w-full max-w-md mx-4 p-6" @click.stop>
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Alasan Reject</h3>
                    <button type="button" wire:click="closeRejectModal" class="text-gray-400 hover:text-gray-600">
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

                <form wire:submit.prevent="submitRejectBooking" class="space-y-4">
                    <div>
                        <label for="rejectReason" class="block text-sm font-medium text-gray-700 mb-1">
                            Alasan Reject <span class="text-red-500">*</span>
                        </label>
                        <textarea id="rejectReason" wire:model="rejectReason" rows="4"
                            class="block w-full px-3 py-2 bg-white border border-gray-300 rounded-md text-sm focus:ring-blue-500 focus:border-blue-500"
                            placeholder="Silakan isi alasan mengapa booking ini di-reject..."></textarea>
                        @error('rejectReason') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                    </div>

                    <div class="flex gap-3 pt-2">
                        <button type="button" wire:click="closeRejectModal"
                            class="flex-1 px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200">
                            Batal
                        </button>
                        <button type="submit" wire:loading.attr="disabled"
                            class="flex-1 px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-md hover:bg-red-700 disabled:opacity-50">
                            <span wire:loading.remove wire:target="submitRejectBooking">Reject Booking</span>
                            <span wire:loading wire:target="submitRejectBooking">Menyimpan...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

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
                        <div>
                            <div class="mt-1">
                                @if($booking->is_free)
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-purple-100 text-purple-800">Free</span>
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

                    <div class="border-t border-gray-100 pt-4 flex flex-wrap gap-2">
                        @if($booking->isCancellationPending())
                            @if($this->canManageApprovals())
                                <button wire:click="approveCancellation({{ $booking->id }})" wire:confirm="Setujui request pembatalan ini?"
                                    class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium text-white bg-green-600 rounded hover:bg-green-700 transition-colors">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                    Approve Cancel
                                </button>
                                @if($booking->isAttendanceNotYet())
                                    <button wire:click="rejectCancellation({{ $booking->id }})" wire:confirm="Tolak request pembatalan ini?"
                                        class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium text-white bg-gray-500 rounded hover:bg-gray-600 transition-colors">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                        Reject Cancel
                                    </button>
                                @endif
                            @endif
                        @elseif($booking->status === 'pending')
                            @if($this->canManageApprovals())
                                <button wire:click="approveBooking({{ $booking->id }})" wire:confirm="Approve booking ini?"
                                    class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium text-white bg-green-600 rounded hover:bg-green-700 transition-colors">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                    Approve
                                </button>
                                <button wire:click="openRejectModal({{ $booking->id }})"
                                    class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium text-white bg-red-600 rounded hover:bg-red-700 transition-colors">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                    Reject
                                </button>
                            @endif
                        @elseif($booking->status === 'rejected')
                            <span class="text-xs text-red-500">Booking ditolak</span>
                        @elseif($booking->status === 'approved')
                            @if($booking->attendance === 'not_yet')
                                @if(Auth::user()->role === 'admin')
                                    <button wire:click="markAsAttended({{ $booking->id }})" wire:confirm="Tandai booking ini sebagai hadir?"
                                        wire:loading.attr="disabled" wire:target="markAsAttended({{ $booking->id }})"
                                        class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium text-white bg-green-600 rounded hover:bg-green-700 transition-colors disabled:pointer-events-none disabled:opacity-50">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                        Tandai Hadir
                                    </button>
                                @endif
                                <button wire:click="openCancelModal({{ $booking->id }})"
                                    class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium text-white bg-red-600 rounded hover:bg-red-700 transition-colors">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                    Batal
                                </button>
                                <button wire:click="markAsNoshow({{ $booking->id }})" wire:confirm="Tandai booking ini sebagai hangus? Sesi akan berkurang."
                                    class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium text-white bg-orange-500 rounded hover:bg-orange-600 transition-colors">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                                    Hangus
                                </button>
                            @elseif($booking->attendance === 'noshow')
                                <button wire:click="restoreNoshow({{ $booking->id }})" wire:confirm="Restore booking ini? Sesi akan dikembalikan."
                                    class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium text-white bg-blue-500 rounded hover:bg-blue-600 transition-colors">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/></svg>
                                    Restore
                                </button>
                            @endif
                        @endif

                        @if(in_array($booking->status, ['approved', 'pending']))
                            <button wire:click="openChangeCoachModal({{ $booking->id }})"
                                class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium text-white bg-indigo-600 rounded hover:bg-indigo-700 transition-colors">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                Edit
                            </button>
                        @endif

                        @if(! in_array($booking->attendance, ['attended', 'noshow']) && ! (Auth::user()->role === 'kasir_gym' && $booking->status === 'approved'))
                            <button wire:click="deleteBooking({{ $booking->id }})" wire:confirm="Hapus booking ini? Data tidak bisa dikembalikan."
                                class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium text-white bg-gray-600 rounded hover:bg-gray-700 transition-colors ml-auto">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                Hapus
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if($showChangeCoachModal)
        <div class="fixed inset-0 z-[60] flex items-center justify-center bg-black/50 backdrop-blur-sm" wire:click.self="closeChangeCoachModal">
            <div class="bg-white rounded-lg shadow-xl w-full max-w-md mx-4 p-6" @click.stop>
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Edit Booking</h3>
                    <button type="button" wire:click="closeChangeCoachModal" class="text-gray-400 hover:text-gray-600">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>

                @php
                    $changeBooking = \App\Models\PtBooking::with(['member', 'pt'])->find($changeCoachBookingId);
                @endphp

                @if($changeBooking)
                    <div class="mb-4 p-3 bg-gray-50 border border-gray-200 rounded-md text-sm">
                        <div class="flex justify-between mb-1">
                            <span class="text-gray-600">Member:</span>
                            <span class="font-medium text-gray-900">{{ $changeBooking->member?->name ?? '-' }}</span>
                        </div>
                        <div class="flex justify-between mb-1">
                            <span class="text-gray-600">Tanggal:</span>
                            <span class="font-medium text-gray-900">{{ $changeBooking->booking_date->locale('id')->isoFormat('dddd, D MMM YYYY') }}</span>
                        </div>
                        <div class="flex justify-between mb-1">
                            <span class="text-gray-600">Waktu:</span>
                            <span class="font-medium text-gray-900">{{ $changeBooking->booking_time->format('H:i') }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Coach Saat Ini:</span>
                            <span class="font-medium text-gray-900">{{ $changeBooking->pt?->name ?? '-' }}</span>
                        </div>
                    </div>
                @endif

                <form wire:submit.prevent="saveChangeCoach" class="space-y-4">
                    <div>
                        <label for="newCoachId" class="block text-sm font-medium text-gray-700 mb-1">
                            Coach <span class="text-red-500">*</span>
                        </label>
                        <select id="newCoachId" wire:model="newCoachId"
                            class="block w-full px-3 py-2 bg-white border border-gray-300 rounded-md text-sm focus:ring-blue-500 focus:border-blue-500">
                            <option value="">Pilih Coach</option>
                            @foreach($this->ptList as $pt)
                                <option value="{{ $pt->id }}">{{ $pt->name }}</option>
                            @endforeach
                        </select>
                        @error('newCoachId') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" wire:model="newIsFree" value="1"
                                class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500">
                            <span class="text-sm font-medium text-gray-700">Sesi Gratis</span>
                        </label>
                        <p class="text-xs text-gray-500 mt-1">Centang Sesi Gratis.</p>
                    </div>

                    <div class="flex gap-3 pt-2">
                        <button type="button" wire:click="closeChangeCoachModal"
                            class="flex-1 px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200">
                            Batal
                        </button>
                        <button type="submit" wire:loading.attr="disabled"
                            class="flex-1 px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-md hover:bg-indigo-700 disabled:opacity-50">
                            <span wire:loading.remove wire:target="saveChangeCoach">Simpan Perubahan</span>
                            <span wire:loading wire:target="saveChangeCoach">Menyimpan...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if($showInsertModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm" wire:click.self="closeInsertModal">
            <div class="bg-white rounded-lg shadow-xl w-full max-w-md mx-4 p-6" @click.stop>
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Tambah Booking</h3>
                    <button type="button" wire:click="closeInsertModal" class="text-gray-400 hover:text-gray-600">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>

                @if($errors->has('insertBooking'))
                    <div class="mb-4 p-3 bg-red-100 border border-red-400 text-red-700 rounded text-sm">
                        {{ $errors->first('insertBooking') }}
                    </div>
                @endif

                <form wire:submit.prevent="saveInsertBooking" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Membership <span class="text-red-500">*</span>
                        </label>

                        @if($insertMembershipId)
                            @php $selectedMembership = \App\Models\Membership::with(['user', 'personalTrainer'])->find($insertMembershipId); @endphp
                            @if($selectedMembership)
                                <div class="mb-2 p-2 bg-blue-50 border border-blue-200 rounded-md text-sm text-blue-800 flex justify-between items-center">
                                    <span>{{ $selectedMembership->user?->name ?? '-' }} — {{ $selectedMembership->personalTrainer?->name ?? '-' }} (Sisa: {{ $selectedMembership->remaining_sessions }})</span>
                                    <button type="button" wire:click="$set('insertMembershipId', null)" class="text-blue-600 hover:text-blue-800 text-xs underline">Ganti</button>
                                </div>
                                @php $sessionNumber = $this->getMembershipSessionNumber($insertMembershipId); @endphp
                                <div class="mt-1.5 text-xs text-gray-600">
                                    Booking ini akan menjadi <span class="font-semibold text-gray-900">Sesi ke-{{ $sessionNumber }}</span>
                                </div>
                            @endif
                        @endif

                        @if(!$insertMembershipId)
                            <input type="text" wire:model.live.debounce.300ms="insertMembershipSearch"
                                class="block w-full px-3 py-2 bg-white border border-gray-300 rounded-md text-sm focus:ring-blue-500 focus:border-blue-500"
                                placeholder="Cari member atau coach...">

                            <div class="mt-1 border border-gray-200 rounded-md max-h-48 overflow-y-auto">
                                @forelse($this->filteredMemberships as $membership)
                                    <div wire:click="selectMembership({{ $membership->id }})"
                                        class="cursor-pointer px-3 py-2 text-sm hover:bg-gray-100 transition-colors border-b border-gray-100 last:border-0">
                                        {{ $membership->user?->name ?? '-' }} — {{ $membership->personalTrainer?->name ?? '-' }} (Sisa: {{ $membership->remaining_sessions }})
                                    </div>
                                @empty
                                    <div class="px-3 py-2 text-sm text-gray-500">Tidak ada membership ditemukan.</div>
                                @endforelse
                            </div>
                        @endif

                        @error('insertMembershipId') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label for="insertPtId" class="block text-sm font-medium text-gray-700 mb-1">
                            Coach <span class="text-red-500">*</span>
                        </label>
                        <select id="insertPtId" wire:model.live="insertPtId"
                            class="block w-full px-3 py-2 bg-white border border-gray-300 rounded-md text-sm focus:ring-blue-500 focus:border-blue-500">
                            <option value="">Pilih Coach</option>
                            @foreach($this->ptList as $pt)
                                <option value="{{ $pt->id }}">{{ $pt->name }}</option>
                            @endforeach
                        </select>
                        @error('insertPtId') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label for="insertType" class="block text-sm font-medium text-gray-700 mb-1">Tipe Booking <span class="text-red-500">*</span></label>
                        <select id="insertType" wire:model="insertType"
                            class="block w-full px-3 py-2 bg-white border border-gray-300 rounded-md text-sm focus:ring-blue-500 focus:border-blue-500">
                            <option value="fleksibel">Fleksibel</option>
                            <option value="keep">Keep</option>
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal</label>
                            <input type="text" readonly value="{{ \Carbon\Carbon::parse($insertDate)->locale('id')->isoFormat('dddd, D MMM YYYY') }}"
                                class="block w-full px-3 py-2 bg-gray-100 border border-gray-300 rounded-md text-sm text-gray-700">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Waktu</label>
                            <input type="text" readonly value="{{ \Carbon\Carbon::parse($insertTime)->format('H:i') }}"
                                class="block w-full px-3 py-2 bg-gray-100 border border-gray-300 rounded-md text-sm text-gray-700">
                        </div>
                    </div>

                    <div>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" wire:model="insertIsFree" value="1"
                                class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500">
                            <span class="text-sm font-medium text-gray-700">Sesi Gratis</span>
                        </label>
                        <p class="text-xs text-gray-500 mt-1">Centang jika Sesi Gratis</p>
                    </div>

                    <div class="flex gap-3 pt-2">
                        <button type="button" wire:click="closeInsertModal"
                            class="flex-1 px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200">
                            Batal
                        </button>
                        <button type="submit" wire:loading.attr="disabled"
                            class="flex-1 px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 disabled:opacity-50">
                            <span wire:loading.remove wire:target="saveInsertBooking">Simpan Booking</span>
                            <span wire:loading wire:target="saveInsertBooking">Menyimpan...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
