<?php

use App\Actions\CancelMemberPtBooking;
use App\Actions\CreateMemberPtBooking;
use App\MemberPtSchedule;
use App\Models\Membership;
use App\Models\PtBooking;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

new #[Layout('layouts::member')] class extends Component
{
    public ?int $selectedMembershipId = null;
    #[Locked]
    public string $weekStart = '';
    public string $dateFrom = '';
    public string $dayView = 'all';
    public string $statusFilter = '';
    public bool $showBookingModal = false;
    public bool $showDetailModal = false;
    public bool $showCancelModal = false;
    public string $cancelReason = '';
    #[Locked]
    public ?int $cancelBookingId = null;
    #[Locked]
    public ?int $selectedBookingId = null;
    #[Locked]
    public string $bookingDate = '';
    #[Locked]
    public string $bookingTime = '';

    public function mount(): void
    {
        abort_unless(Auth::user()->role === 'member', 403);
        $this->thisWeek();
        $this->selectedMembershipId = $this->memberships->first()?->id;
    }

    /** @return Collection<int, Membership> */
    #[Computed]
    public function memberships(): Collection
    {
        return app(MemberPtSchedule::class)->memberships(Auth::user())
            ->with(['personalTrainer', 'ptPackage'])
            ->orderByRaw("CASE WHEN is_active = 1 AND status = 'active' THEN 0 ELSE 1 END")
            ->orderByDesc('start_date')->orderByDesc('id')->get();
    }

    #[Computed]
    public function membership(): ?Membership
    {
        if ($this->selectedMembershipId === null) {
            return null;
        }

        $membership = $this->memberships->find($this->selectedMembershipId);
        abort_unless($membership, 403);

        return $membership;
    }

    public function updatedSelectedMembershipId(): void
    {
        unset($this->membership);
        $this->membership;
        $this->closeBookingModal();
        $this->closeDetailModal();
        $this->closeCancelModal();
        $this->statusFilter = '';
    }

    public function getWeekStart(): Carbon
    {
        return Carbon::parse($this->weekStart)->startOfWeek(Carbon::MONDAY);
    }

    public function previousWeek(): void
    {
        $this->dayView = 'all';
        $this->setSelectedDate($this->getSelectedDate()->subWeek());
    }

    public function nextWeek(): void
    {
        $this->dayView = 'all';
        $this->setSelectedDate($this->getSelectedDate()->addWeek());
    }

    public function thisWeek(): void
    {
        $this->dayView = 'all';
        $this->setSelectedDate(now());
    }

    public function getSelectedDate(): Carbon
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->dateFrom)) {
            return today();
        }

        [$year, $month, $day] = array_map('intval', explode('-', $this->dateFrom));

        return checkdate($month, $day, $year) ? Carbon::create($year, $month, $day)->startOfDay() : today();
    }

    public function updatedDateFrom(): void
    {
        $this->validate(['dateFrom' => ['required', 'date_format:Y-m-d']]);
        $this->setSelectedDate($this->getSelectedDate());
    }

    public function setDayView(string $dayView): void
    {
        if (in_array($dayView, ['today', 'all'], true)) {
            $this->dayView = $dayView;
        }
    }

    public function previousDay(): void
    {
        $this->dayView = 'today';
        $this->setSelectedDate($this->getSelectedDate()->subDay());
    }

    public function today(): void
    {
        $this->dayView = 'today';
        $this->setSelectedDate(now());
    }

    public function nextDay(): void
    {
        $this->dayView = 'today';
        $this->setSelectedDate($this->getSelectedDate()->addDay());
    }

    private function setSelectedDate(Carbon $date): void
    {
        $this->dateFrom = $date->toDateString();
        $this->weekStart = $date->copy()->startOfWeek(Carbon::MONDAY)->toDateString();
        unset($this->calendar, $this->history);
    }

    #[Computed]
    public function unavailableReason(): ?string
    {
        $schedule = app(MemberPtSchedule::class);

        return $schedule->unavailableReason($this->membership, $this->membership
            ? $schedule->reservations($this->membership)->count() : 0);
    }

    /** @return array<int, array{date: string, label: string, slots: array<int, array<string, mixed>>}> */
    #[Computed]
    public function calendar(): array
    {
        $start = $this->getWeekStart();
        $membership = $this->membership;
        $ownedIds = $this->memberships->modelKeys();
        $bookings = $membership?->pt_id
            ? PtBooking::query()->where('pt_id', $membership->pt_id)
                ->whereBetween('booking_date', [$start->toDateString(), $start->copy()->addDays(6)->toDateString()])
                ->where(function (Builder $query) use ($ownedIds): void {
                    $query->whereIn('status', ['pending', 'approved'])
                        ->orWhere(function (Builder $query) use ($ownedIds): void {
                            $query->whereIn('membership_id', $ownedIds)
                                ->whereIn('status', ['cancelled', 'rejected']);
                        });
                })
                ->get(['id', 'membership_id', 'booking_date', 'booking_time', 'status', 'cancellation_requested_at'])
            : collect();
        $schedule = app(MemberPtSchedule::class);
        $days = [];

        for ($day = 0; $day < 7; $day++) {
            $date = $start->copy()->addDays($day);
            $dailyBookings = $bookings->filter(fn (PtBooking $booking): bool => $booking->booking_date->isSameDay($date));
            $slots = [];

            foreach (range(7, 22) as $hour) {
                $slotStart = $date->copy()->setTime($hour, 0);
                $slotEnd = $slotStart->copy()->addHour();
                $overlaps = $dailyBookings->filter(function (PtBooking $booking) use ($slotStart, $slotEnd): bool {
                    $bookingStart = $booking->booking_date->copy()->setTimeFrom($booking->booking_time);

                    return $bookingStart->lt($slotEnd) && $bookingStart->copy()->addHour()->gt($slotStart);
                });
                $own = $overlaps->filter(fn (PtBooking $booking): bool => in_array($booking->membership_id, $ownedIds, true));
                $active = $overlaps->whereIn('status', ['pending', 'approved']);
                $slots[] = [
                    'time' => $slotStart->format('H:i'),
                    'label' => $slotStart->format('H:i').' - '.$slotEnd->format('H:i'),
                    'occupied' => $active->isNotEmpty(),
                    'otherBooked' => $active->contains(fn (PtBooking $booking): bool => ! in_array($booking->membership_id, $ownedIds, true)),
                    'ownBookings' => $own->map(fn (PtBooking $booking): array => [
                        'id' => $booking->id,
                        'status' => $booking->isCancellationPending() ? 'Pending Cancel' : ucfirst($booking->status),
                    ])->values()->all(),
                    'reason' => $this->unavailableReason ?? ($membership ? $schedule->dateUnavailableReason($membership, $slotStart) : null),
                ];
            }

            $days[] = ['date' => $date->toDateString(), 'label' => $date->locale('id')->isoFormat('dddd, D MMM'), 'slots' => $slots];
        }

        return $days;
    }

    /** @return Builder<PtBooking> */
    private function ownBookings(): Builder
    {
        return PtBooking::query()->whereIn('membership_id', app(MemberPtSchedule::class)->memberships(Auth::user())->select('id'));
    }

    /** @return Collection<int, PtBooking> */
    #[Computed]
    public function history(): Collection
    {
        $start = $this->getWeekStart();
        $query = $this->ownBookings()->with('pt')->where('membership_id', $this->selectedMembershipId)
            ->whereBetween('booking_date', [$start->toDateString(), $start->copy()->addDays(6)->toDateString()]);

        if ($this->statusFilter === 'pending_cancel') {
            $query->where('status', 'approved')->whereNotNull('cancellation_requested_at');
        } elseif ($this->statusFilter !== '') {
            $query->where('status', $this->statusFilter);
        }

        return $query->orderBy('booking_date')->orderBy('booking_time')->get();
    }

    public function openBookingModal(string $date, string $time): void
    {
        $this->resetErrorBag();
        $this->bookingDate = $date;
        $this->bookingTime = $time;
        $this->validate([
            'bookingDate' => ['required', 'date_format:Y-m-d'],
            'bookingTime' => ['required', 'date_format:H:i', 'in:'.implode(',', array_map(fn (int $hour): string => sprintf('%02d:00', $hour), range(7, 22)))],
        ]);
        $membership = $this->membership;
        $start = Carbon::parse($date.' '.$time);
        $reason = $this->unavailableReason ?? ($membership ? app(MemberPtSchedule::class)->dateUnavailableReason($membership, $start) : null);

        if ($reason !== null) {
            $this->addError('booking', $reason);

            return;
        }

        if (app(MemberPtSchedule::class)->overlappingBookings($membership->pt_id, $start)->exists()) {
            $this->addError('booking', 'Slot ini sudah terbooking. Silakan pilih jam lain.');

            return;
        }

        $this->showBookingModal = true;
    }

    public function book(CreateMemberPtBooking $createBooking): void
    {
        try {
            $createBooking->execute(Auth::user(), $this->selectedMembershipId ?? 0, $this->bookingDate, $this->bookingTime);
        } catch (ValidationException $exception) {
            $this->showBookingModal = false;
            throw $exception;
        } finally {
            unset($this->memberships, $this->membership, $this->calendar, $this->history, $this->unavailableReason);
        }

        $this->closeBookingModal();
        session()->flash('success', 'Booking berhasil diajukan. Menunggu persetujuan coach/admin.');
    }

    public function closeBookingModal(): void
    {
        $this->showBookingModal = false;
        $this->bookingDate = '';
        $this->bookingTime = '';
        $this->resetErrorBag();
    }

    #[Computed]
    public function selectedBooking(): ?PtBooking
    {
        $booking = $this->selectedBookingId ? $this->ownBookings()
            ->with(['member', 'pt', 'membership.ptPackage', 'membership.members', 'cancelledBy'])
            ->find($this->selectedBookingId) : null;
        abort_if($this->selectedBookingId !== null && $booking === null, 404);

        return $booking;
    }

    public function openDetailModal(int $bookingId): void
    {
        abort_unless($this->ownBookings()->whereKey($bookingId)->exists(), 404);
        $this->selectedBookingId = $bookingId;
        unset($this->selectedBooking);
        $this->showDetailModal = true;
    }

    public function closeDetailModal(): void
    {
        $this->showDetailModal = false;
        $this->selectedBookingId = null;
    }

    public function openCancelModal(int $bookingId): void
    {
        $booking = $this->ownBookings()->find($bookingId);
        abort_unless($booking, 404);
        $this->resetErrorBag();

        if ($reason = app(MemberPtSchedule::class)->cancellationUnavailableReason($booking)) {
            $this->addError('cancelReason', $reason);

            return;
        }

        $this->cancelBookingId = $booking->id;
        $this->selectedBookingId = $booking->id;
        unset($this->selectedBooking);
        $this->cancelReason = '';
        $this->showDetailModal = false;
        $this->showCancelModal = true;
    }

    public function cancelBooking(CancelMemberPtBooking $cancelBooking): void
    {
        $booking = $cancelBooking->execute(Auth::user(), $this->cancelBookingId ?? 0, $this->cancelReason);
        $this->closeCancelModal();
        $this->closeDetailModal();
        unset($this->selectedBooking, $this->calendar, $this->history, $this->unavailableReason);
        session()->flash('success', $booking->isCancellationPending()
            ? 'Permintaan pembatalan dikirim ke coach/admin. Slot tetap terisi sampai disetujui.'
            : 'Booking berhasil dibatalkan.');
    }

    public function closeCancelModal(): void
    {
        $this->showCancelModal = false;
        $this->cancelBookingId = null;
        $this->cancelReason = '';
        $this->resetErrorBag();
    }
}; ?>

<div>
    @if(session()->has('success'))
        <div role="status" class="mb-4 p-3 bg-green-100 border border-green-400 text-green-700 rounded text-sm">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div role="alert" class="mb-4 p-3 bg-red-100 border border-red-400 text-red-700 rounded text-sm">{{ $errors->first() }}</div>
    @endif

    <div class="flex sm:flex-row flex-col justify-between items-center mb-6">
        <h1 class="text-xl font-semibold text-heading">Jadwal PT Saya</h1>
    </div>
    <div x-data="bookingDayFilter" class="relative overflow-hidden bg-neutral-primary-soft shadow-xs rounded-md border border-default">
        <div class="p-4 space-y-3">
        <div>
            <label for="pt-membership" class="mb-2 block text-sm font-medium text-heading">Paket PT dan coach</label>
            <select id="pt-membership" wire:model.live="selectedMembershipId" class="w-full rounded-md border border-default-medium bg-neutral-secondary-medium p-2.5 text-sm text-heading" @disabled($this->memberships->isEmpty())>
                @forelse($this->memberships as $membership)
                    <option value="{{ $membership->id }}">{{ $membership->ptPackage?->name ?? $membership->package_name ?? 'Paket PT' }} #{{ $membership->id }} · {{ $membership->personalTrainer?->name ?? 'Coach belum ditentukan' }}</option>
                @empty
                    <option value="">Tidak ada membership PT</option>
                @endforelse
            </select>
            @if($this->membership)
                <p class="mt-2 text-sm text-body">Coach: {{ $this->membership->personalTrainer?->name ?? 'Belum ditentukan' }}</p>
            @endif
        </div>

            <p class="text-sm text-body">Booking hanya untuk besok, {{ today(config('app.timezone'))->addDay()->locale('id')->isoFormat('dddd, D MMMM YYYY') }}. Setiap sesi berlangsung 60 menit.</p>
            <p class="text-sm text-body">{{ $this->unavailableReason ?? 'Booking baru menunggu persetujuan coach/admin.' }}</p>
        </div>
        <div class="p-4 border-t border-default-medium flex flex-col md:flex-row items-center justify-between gap-4">
            <div class="flex w-full flex-wrap items-center gap-2 md:w-auto">
                <button wire:click="previousDay" x-on:click="dayView = 'today'" x-show="dayView === 'today'" class="inline-flex items-center gap-1 px-3 py-2 text-sm font-medium text-body bg-neutral-secondary-medium border border-default-medium rounded hover:bg-neutral-secondary-dark transition-colors sm:hidden">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
                    Kemarin
                </button>
                <button wire:click="today" x-on:click="dayView = 'today'" x-show="dayView === 'today'" class="inline-flex items-center gap-1 px-3 py-2 text-sm font-medium text-white bg-brand border border-brand rounded hover:bg-brand-dark transition-colors sm:hidden">
                    Hari Ini
                </button>
                <button wire:click="nextDay" x-on:click="dayView = 'today'" x-show="dayView === 'today'" class="inline-flex items-center gap-1 px-3 py-2 text-sm font-medium text-body bg-neutral-secondary-medium border border-default-medium rounded hover:bg-neutral-secondary-dark transition-colors sm:hidden">
                    Besok
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                </button>
                <button wire:click="previousWeek" x-on:click="dayView = 'all'" x-show="dayView === 'all'" class="hidden items-center gap-1 px-3 py-2 text-sm font-medium text-body bg-neutral-secondary-medium border border-default-medium rounded hover:bg-neutral-secondary-dark transition-colors sm:inline-flex">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
                    Minggu Lalu
                </button>
                <button wire:click="thisWeek" x-on:click="dayView = 'all'" x-show="dayView === 'all'" class="hidden items-center gap-1 px-3 py-2 text-sm font-medium text-white bg-brand border border-brand rounded hover:bg-brand-dark transition-colors sm:inline-flex">
                    Minggu Ini
                </button>
                <button wire:click="nextWeek" x-on:click="dayView = 'all'" x-show="dayView === 'all'" class="hidden items-center gap-1 px-3 py-2 text-sm font-medium text-body bg-neutral-secondary-medium border border-default-medium rounded hover:bg-neutral-secondary-dark transition-colors sm:inline-flex">
                    Minggu Depan
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                </button>
                <input type="date" wire:model.change.live="dateFrom"
                    x-on:change="selectDate"
                    class="px-3 py-2 text-sm font-medium text-heading bg-neutral-secondary-medium border border-default-medium rounded focus:ring-brand focus:border-brand shadow-xs"
                    aria-label="Pilih tanggal jadwal" title="Pilih tanggal jadwal">
            </div>
            <div class="hidden w-full items-center justify-center sm:flex md:w-auto md:justify-end">
                <span x-show="dayView === 'all'" class="text-sm font-medium text-heading">
                    {{ $this->getWeekStart()->locale('id')->isoFormat('D MMM YYYY') }} - {{ $this->getWeekStart()->copy()->addDays(6)->locale('id')->isoFormat('D MMM YYYY') }}
                </span>
            </div>
        </div>


        @php
            $calendar = $this->calendar;
            $selectedDate = $this->getSelectedDate()->toDateString();
        @endphp
        <div class="overflow-hidden">
            <table data-responsive-table data-responsive-breakpoint="xl" data-booking-schedule
                x-bind:data-day-view="dayView" aria-label="Kalender slot PT"
                class="table-fixed w-full text-sm text-left text-body border-collapse">
                <caption data-booking-schedule-today-header x-show="dayView === 'today'"
                    class="text-left text-sm font-semibold text-heading sm:hidden">
                    {{ $this->getSelectedDate()->locale('id')->isoFormat('dddd, D MMMM YYYY') }}
                </caption>
                <thead class="text-sm text-body bg-neutral-secondary-medium border-b border-default-medium">
                    <tr>
                        <th scope="col" class="px-4 py-3 font-medium border-r border-default-medium w-28">Time</th>
                        @foreach($calendar as $day)
                            <th scope="col" data-date="{{ $day['date'] }}" wire:key="heading-{{ $day['date'] }}"
                                class="{{ $day['date'] === $selectedDate ? '' : 'hidden sm:table-cell' }} px-4 py-3 font-medium text-center border-r border-default-medium">
                                {{ Carbon::parse($day['date'])->locale('id')->isoFormat('dddd') }}
                                <div class="text-xs font-normal text-body mt-0.5">{{ Carbon::parse($day['date'])->locale('id')->isoFormat('D MMM') }}</div>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach(range(0, 15) as $slotIndex)
                        <tr wire:key="slot-row-{{ $weekStart }}-{{ $selectedMembershipId }}-{{ $slotIndex }}" class="bg-neutral-primary-soft border-b border-default hover:bg-neutral-secondary-medium/50">
                            <td class="px-4 py-3 font-medium text-heading border-r border-default-medium bg-neutral-secondary-medium/30">{{ $calendar[0]['slots'][$slotIndex]['label'] }}</td>
                            @foreach($calendar as $day)
                                @php
                                    $slot = $day['slots'][$slotIndex];
                                @endphp
                                <td data-date="{{ $day['date'] }}" wire:key="slot-{{ $selectedMembershipId }}-{{ $day['date'] }}-{{ $slotIndex }}"
                                    class="{{ $day['date'] === $selectedDate ? '' : 'hidden sm:table-cell' }} min-w-0 max-w-full overflow-hidden px-2 py-2 border-r border-default align-top">
                                    <div class="flex w-full min-w-0 max-w-full flex-col gap-1.5">
                                        @foreach($slot['ownBookings'] as $ownBooking)
                                            @php
                                                $cardStyle = match ($ownBooking['status']) {
                                                    'Pending' => 'bg-orange-50 border-orange-200',
                                                    'Pending Cancel' => 'bg-yellow-50 border-yellow-200',
                                                    'Cancelled' => 'bg-gray-50 border-gray-200',
                                                    'Rejected' => 'bg-red-50 border-red-200',
                                                    default => 'bg-green-50 border-green-200',
                                                };
                                                $badgeStyle = match ($ownBooking['status']) {
                                                    'Pending' => 'bg-orange-100 text-orange-800',
                                                    'Pending Cancel' => 'bg-yellow-100 text-yellow-800',
                                                    'Cancelled' => 'bg-gray-100 text-gray-600',
                                                    'Rejected' => 'bg-red-100 text-red-800',
                                                    default => 'bg-green-100 text-green-800',
                                                };
                                            @endphp
                                            <button type="button" wire:key="own-{{ $day['date'] }}-{{ $slotIndex }}-{{ $ownBooking['id'] }}" wire:click="openDetailModal({{ $ownBooking['id'] }})"
                                                class="w-full min-w-0 max-w-full overflow-hidden cursor-pointer p-2 rounded border text-xs text-left transition-colors {{ $cardStyle }}">
                                                <span class="block whitespace-normal wrap-anywhere font-semibold text-heading">Booking Saya</span>
                                                <span class="mt-0.5 block whitespace-normal wrap-anywhere text-body">{{ $this->membership?->personalTrainer?->name }}</span>
                                                <span class="mt-1 inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium {{ $badgeStyle }}">{{ $ownBooking['status'] }}</span>
                                            </button>
                                        @endforeach
                                        @if($slot['otherBooked'])
                                            <div class="w-full min-w-0 max-w-full overflow-hidden p-2 rounded border border-gray-200 bg-gray-50 text-xs">
                                                <p class="whitespace-normal wrap-anywhere font-semibold text-body">Dibooking member lain</p>
                                            </div>
                                        @elseif(! $slot['occupied'])
                                            @if($slot['reason'])
                                                <p class="min-h-[60px] p-2 text-xs whitespace-normal wrap-anywhere text-body">{{ $slot['reason'] }}</p>
                                            @else
                                                <button type="button" wire:click="openBookingModal('{{ $day['date'] }}', '{{ $slot['time'] }}')" wire:loading.attr="disabled"
                                                    aria-label="Booking {{ $day['label'] }} {{ $slot['label'] }}" title="Booking"
                                                    class="w-full min-w-0 max-w-full min-h-[60px] overflow-hidden cursor-pointer bg-brand/5 hover:bg-brand/10 rounded flex items-center justify-center transition-colors disabled:opacity-50">
                                                    <svg class="w-5 h-5 text-brand" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
                                                </button>
                                            @endif
                                        @endif
                                    </div>
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <section class="mt-6 rounded-md border border-default bg-neutral-primary-soft p-4 shadow-xs" aria-label="Riwayat booking sendiri">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <h2 class="font-semibold text-heading">Booking paket saya minggu ini</h2>
            <div>
                <label for="pt-history-status" class="sr-only">Filter status riwayat</label>
                <select id="pt-history-status" wire:model.live="statusFilter" class="rounded-md border border-default-medium bg-neutral-secondary-medium p-2 text-sm text-heading">
                    <option value="">Semua Status</option>
                    <option value="pending">Pending</option>
                    <option value="approved">Approved</option>
                    <option value="cancelled">Cancelled</option>
                    <option value="rejected">Rejected</option>
                    <option value="pending_cancel">Pending Cancel</option>
                </select>
            </div>
        </div>
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @forelse($this->history as $booking)
                <button type="button" wire:key="history-{{ $booking->id }}" wire:click="openDetailModal({{ $booking->id }})" class="rounded-md border border-default p-3 text-left text-sm hover:bg-neutral-secondary-medium">
                    <span class="block font-medium text-heading">{{ $booking->booking_date->locale('id')->isoFormat('dddd, D MMM') }} · {{ $booking->booking_time->format('H:i') }} - {{ $booking->booking_time->copy()->addHour()->format('H:i') }}</span>
                    <span class="mt-1 block text-body">{{ $booking->pt?->name ?? '-' }} · {{ $booking->isCancellationPending() ? 'Pending Cancel' : ucfirst($booking->status) }}</span>
                </button>
            @empty
                <p class="text-sm text-body">Tidak ada booking sesuai filter pada minggu ini.</p>
            @endforelse
        </div>
    </section>

    @if($showBookingModal && $this->membership)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-sm" wire:click.self="closeBookingModal" x-on:keydown.escape.window="$wire.closeBookingModal()">
            <section role="dialog" aria-modal="true" aria-labelledby="booking-title" class="w-full max-w-lg max-h-[90vh] overflow-y-auto rounded-lg bg-white p-6 shadow-xl" x-trap.inert.noscroll="true">
                <h2 id="booking-title" class="text-lg font-semibold text-heading">Konfirmasi booking PT</h2>
                <dl class="my-4 space-y-2 text-sm text-heading">
                    <div><dt class="text-body">Paket</dt><dd>{{ $this->membership->ptPackage?->name ?? $this->membership->package_name ?? 'Paket PT' }}</dd></div>
                    <div><dt class="text-body">Coach</dt><dd>{{ $this->membership->personalTrainer?->name }}</dd></div>
                    <div><dt class="text-body">Tanggal dan jam</dt><dd>{{ Carbon::parse($bookingDate)->locale('id')->isoFormat('dddd, D MMM YYYY') }} · {{ $bookingTime }} - {{ Carbon::parse($bookingTime)->addHour()->format('H:i') }}</dd></div>
                </dl>
                <p class="text-sm text-body">Satu sesi akan dipesan dan menunggu persetujuan coach/admin.</p>
                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" wire:click="closeBookingModal" class="rounded-md border border-default px-4 py-2 text-sm text-heading">Batal</button>
                    <button type="button" wire:click="book" wire:loading.attr="disabled" class="rounded-md bg-brand px-4 py-2 text-sm font-medium text-[#34342F] disabled:opacity-50"><span wire:loading.remove wire:target="book">Ajukan Booking</span><span wire:loading wire:target="book">Menyimpan...</span></button>
                </div>
            </section>
        </div>
    @endif

    @if($showCancelModal && $this->selectedBooking)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-sm" wire:click.self="closeCancelModal" x-on:keydown.escape.window="$wire.closeCancelModal()">
            <section role="dialog" aria-modal="true" aria-labelledby="cancel-title" class="w-full max-w-md max-h-[90vh] overflow-y-auto rounded-lg bg-white p-6 shadow-xl" x-trap.inert.noscroll="true">
                <h2 id="cancel-title" class="text-lg font-semibold text-heading">{{ $this->selectedBooking->isApproved() ? 'Ajukan Pembatalan' : 'Batalkan Booking' }}</h2>
                <p class="mt-2 text-sm text-body">{{ $this->selectedBooking->booking_date->locale('id')->isoFormat('dddd, D MMM YYYY') }} · {{ $this->selectedBooking->booking_time->format('H:i') }}</p>
                <p class="mt-2 text-sm text-body">{{ $this->selectedBooking->isApproved() ? 'Pembatalan memerlukan persetujuan coach/admin. Slot tetap terisi sampai disetujui.' : 'Booking pending akan langsung dibatalkan dan tidak lagi menahan kuota.' }}</p>
                <form wire:submit="cancelBooking" class="mt-4 space-y-4">
                    <div>
                        <label for="cancel-reason" class="mb-1 block text-sm font-medium text-heading">Alasan pembatalan</label>
                        <textarea id="cancel-reason" wire:model="cancelReason" rows="3" minlength="5" maxlength="500" required class="block w-full rounded-md border border-default-medium p-2.5 text-sm text-heading focus:ring-brand focus:border-brand"></textarea>
                        @error('cancelReason') <p role="alert" class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div class="flex gap-3">
                        <button type="button" wire:click="closeCancelModal" class="flex-1 rounded-md bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200">Kembali</button>
                        <button type="submit" wire:loading.attr="disabled" class="flex-1 rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-50">{{ $this->selectedBooking->isApproved() ? 'Ajukan Pembatalan' : 'Batalkan Booking' }}</button>
                    </div>
                </form>
            </section>
        </div>
    @endif

    @if($showDetailModal && $this->selectedBooking)
        @php
            $booking = $this->selectedBooking;
            $cancelUnavailableReason = app(MemberPtSchedule::class)->cancellationUnavailableReason($booking);
        @endphp
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
                            <div class="font-medium text-heading">{{ $booking->booking_time->format('H:i') }} - {{ $booking->booking_time->copy()->addHour()->format('H:i') }}</div>
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
                    @if(in_array($booking->status, ['pending', 'approved'], true) && ! $booking->isCancellationPending())
                        <div class="border-t border-gray-100 pt-4">
                            <button type="button" wire:click="openCancelModal({{ $booking->id }})" @disabled($cancelUnavailableReason !== null) aria-describedby="cancel-availability" class="rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-50">{{ $booking->isApproved() ? 'Ajukan Pembatalan' : 'Batalkan Booking' }}</button>
                            <p id="cancel-availability" class="mt-2 text-sm text-body">{{ $cancelUnavailableReason ?? ($booking->isApproved() ? 'Pembatalan memerlukan persetujuan coach/admin.' : 'Booking pending akan langsung dibatalkan.') }}</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
