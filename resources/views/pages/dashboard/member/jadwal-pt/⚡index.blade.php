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
    public string $dayView = 'today';
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
        $this->today();
        $this->selectedMembershipId = $this->memberships->first()?->id;
    }

    /** @return Collection<int, Membership> */
    #[Computed]
    public function memberships(): Collection
    {
        return app(MemberPtSchedule::class)->memberships(Auth::user())
            ->where('status', 'active')
            ->where('is_active', true)
            ->with(['personalTrainer', 'ptPackage'])
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

    /** @return array<int, array{date: string, label: string, bookingCount: int, slots: array<int, array<string, mixed>>}> */
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
            $dailyLimitReason = $schedule->dailyBookings(Auth::user(), $date->toDateString())->exists()
                ? 'Sudah ada booking pending atau approved pada tanggal ini. Maksimal 1 booking per hari.' : null;
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
                    'reason' => $this->unavailableReason ?? ($membership ? $schedule->dateUnavailableReason($membership, $slotStart) : null) ?? $dailyLimitReason,
                ];
            }

            $days[] = [
                'date' => $date->toDateString(),
                'label' => $date->locale('id')->isoFormat('dddd, D MMM'),
                'bookingCount' => $dailyBookings->where('membership_id', $membership?->id)->whereIn('status', ['pending', 'approved'])->count(),
                'slots' => $slots,
            ];
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

        if (app(MemberPtSchedule::class)->dailyBookings(Auth::user(), $date)->exists()) {
            $this->addError('booking', 'Sudah ada booking pending atau approved pada tanggal ini. Maksimal 1 booking per hari.');

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

<main x-data class="member-pt-schedule mx-auto w-full max-w-[1200px] px-4 pb-10 sm:px-6">
    <header class="relative isolate -mx-4 flex min-h-56 flex-col justify-end overflow-hidden px-4 pb-8 pt-12 sm:-mx-6 sm:rounded-t-3xl sm:px-6 sm:pt-16">
        <img src="{{ asset('ruangan.webp') }}" alt="" aria-hidden="true" class="absolute inset-0 -z-20 size-full object-cover opacity-60">
        <div class="absolute inset-0 -z-10 bg-linear-to-t from-[#080c0e] via-[#080c0e]/25 to-transparent" aria-hidden="true"></div>
        <h1 class="text-3xl font-extrabold tracking-tight text-white sm:text-4xl">Jadwal Booking</h1>
        <p class="mt-2 text-lg text-gray-300">Personal Training</p>
    </header>

    @if(session()->has('success'))
        <div role="status" class="mb-4 rounded-xl border border-emerald-700 bg-emerald-950 p-4 text-sm text-emerald-200">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div role="alert" class="mb-4 rounded-xl border border-red-700 bg-red-950 p-4 text-sm text-red-200">{{ $errors->first() }}</div>
    @endif

    <section class="mb-5 rounded-2xl border border-white/15 bg-[#121719] p-4 sm:p-5" aria-label="Paket dan ketentuan booking">
        <label for="pt-membership" class="mb-2 block text-sm font-semibold text-white">Paket PT dan coach</label>
        <select id="pt-membership" wire:model.live="selectedMembershipId" class="pt-schedule-control w-full" @disabled($this->memberships->isEmpty())>
            @forelse($this->memberships as $membership)
                <option value="{{ $membership->id }}">{{ $membership->ptPackage?->name ?? $membership->package_name ?? 'Paket PT' }} #{{ $membership->id }} · {{ $membership->personalTrainer?->name ?? 'Coach belum ditentukan' }}</option>
            @empty
                <option value="">Tidak ada membership PT</option>
            @endforelse
        </select>
        @if($this->membership)
            <p class="mt-3 text-sm text-gray-300">Coach: {{ $this->membership->personalTrainer?->name ?? 'Belum ditentukan' }}</p>
        @endif
        <p class="mt-3 text-xs leading-6 text-gray-400">Booking tersedia mulai hari ini sampai 7 hari ke depan, selama sesi belum dimulai. Maksimal 1 booking pending atau approved per hari. Setiap sesi berlangsung 60 menit.</p>
        <p class="mt-2 text-xs leading-6 text-yellow-200">{{ $this->unavailableReason ?? 'Booking baru menunggu persetujuan coach/admin.' }}</p>
    </section>

    <nav class="space-y-3" aria-label="Navigasi jadwal">
        <div class="flex flex-wrap items-center gap-2">
            <button type="button" wire:click="setDayView('all')" aria-pressed="{{ $dayView === 'all' ? 'true' : 'false' }}" @class(['pt-schedule-control', 'pt-schedule-control-selected' => $dayView === 'all'])>Satu minggu</button>
            <button type="button" wire:click="setDayView('today')" aria-pressed="{{ $dayView === 'today' ? 'true' : 'false' }}" @class(['pt-schedule-control', 'pt-schedule-control-selected' => $dayView === 'today'])>Satu hari</button>
        </div>
        <div class="grid grid-cols-[auto_minmax(0,1fr)_auto] gap-2 sm:flex sm:flex-wrap">
            <button type="button" wire:click="{{ $dayView === 'all' ? 'previousWeek' : 'previousDay' }}" class="pt-schedule-control" aria-label="{{ $dayView === 'all' ? 'Minggu Lalu' : 'Kemarin' }}"><x-member-package-icon name="chevron" class="size-5 rotate-180"/></button>
            <button type="button" wire:click="{{ $dayView === 'all' ? 'thisWeek' : 'today' }}" class="pt-schedule-control">{{ $dayView === 'all' ? 'Minggu Ini' : 'Hari Ini' }}</button>
            <button type="button" wire:click="{{ $dayView === 'all' ? 'nextWeek' : 'nextDay' }}" class="pt-schedule-control" aria-label="{{ $dayView === 'all' ? 'Minggu Depan' : 'Besok' }}"><x-member-package-icon name="chevron" class="size-5"/></button>
            <input type="date" wire:model.change.live="dateFrom" class="pt-schedule-control col-span-3 w-full min-w-0 sm:ml-auto sm:w-auto" aria-label="Pilih tanggal jadwal">
        </div>
        @error('dateFrom') <p class="text-sm text-red-300">{{ $message }}</p> @enderror
        <p class="py-3 text-center text-base font-semibold text-white sm:text-xl">
            @if($dayView === 'all')
                {{ $this->getWeekStart()->locale('id')->isoFormat('D MMM YYYY') }} - {{ $this->getWeekStart()->copy()->addDays(6)->locale('id')->isoFormat('D MMM YYYY') }}
            @else
                {{ $this->getSelectedDate()->locale('id')->isoFormat('dddd, D MMMM YYYY') }}
            @endif
        </p>
    </nav>

    <div data-member-pt-calendar @class(['grid items-start gap-4', 'lg:grid-cols-2' => $dayView === 'all']) aria-label="Kalender slot PT">
        @foreach($this->calendar as $day)
            @if($dayView === 'all' || $day['date'] === $this->getSelectedDate()->toDateString())
                <x-member-pt-day :day="$day" :selected="$day['date'] === $this->getSelectedDate()->toDateString()" :coach="$this->membership?->personalTrainer?->name" wire:key="day-{{ $selectedMembershipId }}-{{ $day['date'] }}" />
            @endif
        @endforeach
    </div>

    <section class="mt-6 rounded-md border border-white/15 bg-[#121719] p-4 shadow-xs" aria-label="Riwayat booking sendiri">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <h2 class="font-semibold text-white">Booking paket saya minggu ini</h2>
            <div>
                <label for="pt-history-status" class="sr-only">Filter status riwayat</label>
                <select id="pt-history-status" wire:model.live="statusFilter" class="pt-schedule-control">
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
                <button type="button" wire:key="history-{{ $booking->id }}" wire:click="openDetailModal({{ $booking->id }})" class="rounded-md border border-white/15 p-3 text-left text-sm hover:bg-[#1d2427]">
                    <span class="block font-medium text-white">{{ $booking->booking_date->locale('id')->isoFormat('dddd, D MMM') }} · {{ $booking->booking_time->format('H:i') }} - {{ $booking->booking_time->copy()->addHour()->format('H:i') }}</span>
                    <span class="mt-1 block text-gray-300">{{ $booking->pt?->name ?? '-' }} · {{ $booking->isCancellationPending() ? 'Pending Cancel' : ucfirst($booking->status) }}</span>
                </button>
            @empty
                <p class="text-sm text-gray-300">Tidak ada booking sesuai filter pada minggu ini.</p>
            @endforelse
        </div>
    </section>

    @if($showBookingModal && $this->membership)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-sm" wire:click.self="closeBookingModal" x-on:keydown.escape.window="$wire.closeBookingModal()">
            <section role="dialog" aria-modal="true" aria-labelledby="booking-title" class="w-full max-w-lg max-h-[90vh] overflow-y-auto rounded-lg bg-[#121719] p-6 shadow-xl" x-trap.inert.noscroll="true">
                <h2 id="booking-title" class="text-lg font-semibold text-white">Konfirmasi booking PT</h2>
                <dl class="my-4 space-y-2 text-sm text-white">
                    <div><dt class="text-gray-300">Paket</dt><dd>{{ $this->membership->ptPackage?->name ?? $this->membership->package_name ?? 'Paket PT' }}</dd></div>
                    <div><dt class="text-gray-300">Coach</dt><dd>{{ $this->membership->personalTrainer?->name }}</dd></div>
                    <div><dt class="text-gray-300">Tanggal dan jam</dt><dd>{{ Carbon::parse($bookingDate)->locale('id')->isoFormat('dddd, D MMM YYYY') }} · {{ $bookingTime }} - {{ Carbon::parse($bookingTime)->addHour()->format('H:i') }}</dd></div>
                </dl>
                <p class="text-sm text-gray-300">Satu sesi akan dipesan dan menunggu persetujuan coach/admin.</p>
                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" wire:click="closeBookingModal" class="rounded-md border border-white/15 px-4 py-2 text-sm text-white">Batal</button>
                    <button type="button" wire:click="book" wire:loading.attr="disabled" class="rounded-md bg-brand px-4 py-2 text-sm font-medium text-[#34342F] disabled:opacity-50"><span wire:loading.remove wire:target="book">Ajukan Booking</span><span wire:loading wire:target="book">Menyimpan...</span></button>
                </div>
            </section>
        </div>
    @endif

    @if($showCancelModal && $this->selectedBooking)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-sm" wire:click.self="closeCancelModal" x-on:keydown.escape.window="$wire.closeCancelModal()">
            <section role="dialog" aria-modal="true" aria-labelledby="cancel-title" class="w-full max-w-md max-h-[90vh] overflow-y-auto rounded-lg bg-[#121719] p-6 shadow-xl" x-trap.inert.noscroll="true">
                <h2 id="cancel-title" class="text-lg font-semibold text-white">{{ $this->selectedBooking->isApproved() ? 'Ajukan Pembatalan' : 'Batalkan Booking' }}</h2>
                <p class="mt-2 text-sm text-gray-300">{{ $this->selectedBooking->booking_date->locale('id')->isoFormat('dddd, D MMM YYYY') }} · {{ $this->selectedBooking->booking_time->format('H:i') }}</p>
                <p class="mt-2 text-sm text-gray-300">{{ $this->selectedBooking->isApproved() ? 'Pembatalan memerlukan persetujuan coach/admin. Slot tetap terisi sampai disetujui.' : 'Booking pending akan langsung dibatalkan dan tidak lagi menahan kuota.' }}</p>
                <form wire:submit="cancelBooking" class="mt-4 space-y-4">
                    <div>
                        <label for="cancel-reason" class="mb-1 block text-sm font-medium text-white">Alasan pembatalan</label>
                        <textarea id="cancel-reason" wire:model="cancelReason" rows="3" minlength="5" maxlength="500" required class="block w-full rounded-md border border-white/15 p-2.5 text-sm text-white focus:ring-brand focus:border-brand"></textarea>
                        @error('cancelReason') <p role="alert" class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div class="flex gap-3">
                        <button type="button" wire:click="closeCancelModal" class="flex-1 rounded-md bg-slate-800 px-4 py-2 text-sm font-medium text-gray-200 hover:bg-slate-700">Kembali</button>
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
            <div class="bg-[#121719] rounded-lg shadow-xl w-full max-w-lg mx-4 max-h-[90vh] overflow-y-auto" role="dialog" aria-modal="true" aria-labelledby="pt-detail-title" x-data x-trap.inert.noscroll="true" x-on:keydown.escape.window="$wire.closeDetailModal()" @click.stop>
                <div class="flex items-center justify-between p-4 border-b border-white/15 sticky top-0 bg-[#121719]">
                    <h3 id="pt-detail-title" class="text-lg font-semibold text-white">Detail Booking</h3>
                    <button type="button" aria-label="Tutup detail booking" wire:click="closeDetailModal" class="text-gray-400 hover:text-gray-300">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <div class="p-4 space-y-4">
                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <span class="text-gray-300">Member</span>
                            <div class="font-medium text-white">{{ $booking->member?->name ?? '-' }}</div>
                        </div>
                        <div>
                            <span class="text-gray-300">Coach</span>
                            <div class="font-medium text-white">{{ $booking->pt?->name ?? '-' }}</div>
                        </div>
                        <div>
                            <span class="text-gray-300">Paket</span>
                            <div class="font-medium text-white">{{ $booking->membership?->ptPackage?->name ?? '-' }}</div>
                        </div>
                        <div>
                            <span class="text-gray-300">Tanggal</span>
                            <div class="font-medium text-white">{{ $booking->booking_date->locale('id')->isoFormat('dddd, D MMM YYYY') }}</div>
                        </div>
                        <div>
                            <span class="text-gray-300">Waktu</span>
                            <div class="font-medium text-white">{{ $booking->booking_time->format('H:i') }} - {{ $booking->booking_time->copy()->addHour()->format('H:i') }}</div>
                        </div>
                        <div>
                            <span class="text-gray-300">Status</span>
                            <div class="mt-1">
                                @if($booking->isCancellationPending())
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-yellow-950 text-yellow-200">Pending Cancel</span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium capitalize
                                        @if($booking->status === 'pending') bg-orange-950 text-orange-200
                                        @elseif($booking->status === 'approved') bg-emerald-950 text-emerald-200
                                        @elseif($booking->status === 'cancelled') bg-slate-800 text-gray-300
                                        @elseif($booking->status === 'rejected') bg-red-950 text-red-200
                                        @else bg-slate-800 text-gray-800
                                        @endif">
                                        {{ $booking->status }}
                                    </span>
                                @endif
                            </div>
                        </div>
                        <div>
                            <span class="text-gray-300">Absensi</span>
                            <div class="mt-1">
                                @if($booking->status === 'approved')
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium capitalize
                                        @if($booking->attendance === 'attended') bg-emerald-950 text-emerald-200
                                        @elseif($booking->attendance === 'noshow') bg-red-950 text-red-200
                                        @else bg-slate-800 text-gray-300
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
                        <div class="border-t border-white/10 pt-3">
                            <span class="text-gray-300 text-sm">Member Lain</span>
                            <div class="flex flex-wrap gap-2 mt-1">
                                @foreach($booking->membership->members->where('id', '!=', $booking->member_id) as $member)
                                    <span class="px-2 py-1 bg-[#1d2427] rounded text-xs text-white">{{ $member->name }}</span>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if($booking->isCancellationPending())
                        <div class="border-t border-white/10 pt-3">
                            <span class="text-yellow-600 font-medium text-sm">Request Pembatalan</span>
                            <div class="text-xs text-gray-300 mt-1">
                                {{ $booking->cancelledBy?->name ?? '-' }} - {{ $booking->cancellation_requested_at->locale('id')->isoFormat('D MMM YYYY HH:mm') }}
                            </div>
                            @if($booking->cancellation_reason)
                                <div class="text-red-600 mt-1 italic text-xs">"{{ $booking->cancellation_reason }}"</div>
                            @endif
                        </div>
                    @elseif($booking->status === 'cancelled' && $booking->cancelled_at)
                        <div class="border-t border-white/10 pt-3">
                            <span class="text-gray-300 font-medium text-sm">Dibatalkan</span>
                            <div class="text-xs text-gray-300 mt-1">
                                {{ $booking->cancelledBy?->name ?? '-' }} - {{ $booking->cancelled_at->locale('id')->isoFormat('D MMM YYYY HH:mm') }}
                            </div>
                            @if($booking->cancellation_reason)
                                <div class="text-gray-500 mt-1 italic text-xs">"{{ $booking->cancellation_reason }}"</div>
                            @endif
                        </div>
                    @elseif($booking->status === 'rejected')
                        <div class="border-t border-white/10 pt-3">
                            <span class="text-red-600 font-medium text-sm">Booking Ditolak</span>
                            <div class="text-xs text-gray-300 mt-1">
                                {{ $booking->rejected_at?->locale('id')->isoFormat('D MMM YYYY HH:mm') ?? '-' }}
                            </div>
                            @if($booking->rejection_reason)
                                <div class="text-red-500 mt-1 italic text-xs">"{{ $booking->rejection_reason }}"</div>
                            @endif
                        </div>
                    @endif
                    @if(in_array($booking->status, ['pending', 'approved'], true) && ! $booking->isCancellationPending())
                        <div class="border-t border-white/10 pt-4">
                            <button type="button" wire:click="openCancelModal({{ $booking->id }})" @disabled($cancelUnavailableReason !== null) aria-describedby="cancel-availability" class="rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-50">{{ $booking->isApproved() ? 'Ajukan Pembatalan' : 'Batalkan Booking' }}</button>
                            <p id="cancel-availability" class="mt-2 text-sm text-gray-300">{{ $cancelUnavailableReason ?? ($booking->isApproved() ? 'Pembatalan memerlukan persetujuan coach/admin.' : 'Booking pending akan langsung dibatalkan.') }}</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif
</main>
