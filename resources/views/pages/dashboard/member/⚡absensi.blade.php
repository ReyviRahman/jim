<?php

namespace App\Livewire\Member;

use Livewire\Component;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use App\Models\Membership;
use App\Models\Attendance;
use App\Models\PtBooking;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

new #[Layout('layouts::member')] class extends Component
{
    // --- TAMBAHAN UNTUK POLLING ---
    public $hasCheckedIn = false;
    public $selectedMembershipId = null;
    public $selectedBookingId = null;

    public function mount(): void
    {
        $rawActiveMemberships = $this->selectableMembershipQuery()
            ->with(['gymPackage', 'ptPackage'])
            ->get();

        $activeMemberships = collect();

        foreach ($rawActiveMemberships as $membership) {
            $isExpired = false;

            if ($membership->type === 'pt') {
                if ($membership->pt_end_date && Carbon::parse($membership->pt_end_date)->endOfDay()->isPast()) {
                    $isExpired = true;
                }

                if (!is_null($membership->remaining_sessions) && $membership->remaining_sessions <= 0) {
                    $isUsedToday = Attendance::where('membership_id', $membership->id)
                        ->where('type', 'pt')
                        ->where('check_in_time', '>=', today()->startOfDay())
                        ->where('check_in_time', '<=', today()->endOfDay())
                        ->exists();

                    if (!$isUsedToday) {
                        $isExpired = true;
                    }
                }
            } elseif ($membership->type === 'bundle_pt_membership') {
                if ($membership->membership_end_date && Carbon::parse($membership->membership_end_date)->endOfDay()->isPast()) {
                    $isExpired = true;
                }
                if (!is_null($membership->remaining_sessions) && $membership->remaining_sessions <= 0) {
                    $isExpired = true;
                }
            } else {
                if ($membership->membership_end_date && Carbon::parse($membership->membership_end_date)->endOfDay()->isPast()) {
                    $isExpired = true;
                }
            }

            if ($isExpired) {
                if ($membership->status !== 'completed') {
                    $membership->update(['status' => 'completed', 'is_active' => false]);
                }
            } else {
                $activeMemberships->push($membership);
            }
        }

        if ($activeMemberships->isNotEmpty() && is_null($this->selectedMembershipId)) {
            $this->selectedMembershipId = $activeMemberships->first()->id;
        }
    }

    public function updatingSelectedMembershipId(mixed $value): void
    {
        $this->validatedSelectableMembershipId($value);
    }

    public function updatedSelectedMembershipId(mixed $value): void
    {
        $this->selectedMembershipId = (int) $value;
        $this->selectedBookingId = null;
    }

    public function updatingSelectedBookingId(mixed $value): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $this->validatedEligibleBookingId($value);
    }

    public function updatedSelectedBookingId(mixed $value): void
    {
        $this->selectedBookingId = $value === null || $value === '' ? null : (int) $value;
    }

    public function selectMembership(mixed $membershipId): void
    {
        $this->selectedMembershipId = $this->validatedSelectableMembershipId($membershipId);
        $this->selectedBookingId = null;
    }

    public function selectBooking(mixed $bookingId): void
    {
        $this->selectedBookingId = $this->validatedEligibleBookingId($bookingId);
    }

    /** @return EloquentCollection<int, PtBooking> */
    public function getEligibleBookingsProperty(): EloquentCollection
    {
        if (!$this->selectedMembershipId) {
            return new EloquentCollection();
        }

        $membership = $this->selectableMembershipQuery()
            ->whereKey($this->selectedMembershipId)
            ->first();

        if (!$membership || $membership->type !== 'pt') {
            return new EloquentCollection();
        }

        return $this->eligibleBookingQuery($membership->getKey())
            ->orderBy('booking_date', 'asc')
            ->get();
    }

    public function checkAttendance(): void
    {
        if (!$this->hasCheckedIn) {
            $recentScan = Attendance::where('user_id', Auth::id())
                ->where('check_in_time', '>=', now()->subSeconds(30))
                ->exists();

            if ($recentScan) {
                $this->hasCheckedIn = true;
            }
        }
    }
    // ------------------------------

    public function with(): array
    {
        $user = $this->authenticatedUser();
        
// 1. Ambil paket 'active' saja
        $rawActiveMemberships = $this->selectableMembershipQuery()
            ->with(['gymPackage', 'ptPackage'])
            ->get();

        // 2. Siapkan collection baru untuk menyimpan paket yang valid tayang
        $activeMemberships = collect();

        // 3. Lakukan pengecekan tanggal & sesi untuk setiap paket
        foreach ($rawActiveMemberships as $membership) {
            $isExpired = false;

            if ($membership->type === 'pt') {
                if ($membership->pt_end_date && Carbon::parse($membership->pt_end_date)->endOfDay()->isPast()) {
                    $isExpired = true;
                }
                
                if (!is_null($membership->remaining_sessions) && $membership->remaining_sessions <= 0) {
                    $isUsedToday = Attendance::where('membership_id', $membership->id)
                        ->where('type', 'pt')
                        ->where('check_in_time', '>=', today()->startOfDay())
                        ->where('check_in_time', '<=', today()->endOfDay())
                        ->exists();

                    if (!$isUsedToday) {
                        $isExpired = true;
                    }
                }
            } elseif ($membership->type === 'bundle_pt_membership') {
                if ($membership->membership_end_date && Carbon::parse($membership->membership_end_date)->endOfDay()->isPast()) {
                    $isExpired = true;
                }
                if (!is_null($membership->remaining_sessions) && $membership->remaining_sessions <= 0) {
                    $isExpired = true;
                }
            } else {
                if ($membership->membership_end_date && Carbon::parse($membership->membership_end_date)->endOfDay()->isPast()) {
                    $isExpired = true;
                }
            }

            if ($isExpired) {
                if ($membership->status !== 'completed') {
                    $membership->update(['status' => 'completed', 'is_active' => false]);
                }
            } else {
                $activeMemberships->push($membership);
            }
        }

        $hasActivePackage = $activeMemberships->isNotEmpty();
        $qrCode = null;
        $selectedMembership = $activeMemberships->firstWhere('id', $this->selectedMembershipId);
        $eligibleBookings = $this->getEligibleBookingsProperty();

        // QR Code generation based on membership type
        if ($hasActivePackage && $this->selectedMembershipId && $selectedMembership) {
            if ($selectedMembership->type === 'pt' && $this->selectedBookingId) {
                $booking = $eligibleBookings->firstWhere('id', $this->selectedBookingId);
                if ($booking) {
                    $qrData = json_encode([
                        'booking_id' => $booking->id,
                        'user_id' => $user->id,
                        'membership_id' => $this->selectedMembershipId
                    ]);
                    $qrCode = QrCode::size(220)->margin(1)->generate($qrData);
                }
            } elseif ($selectedMembership->type !== 'pt') {
                $qrData = json_encode([
                    'user_id' => $user->id,
                    'membership_id' => $this->selectedMembershipId
                ]);
                $qrCode = QrCode::size(220)->margin(1)->generate($qrData);
            }
        }

        return [
            'user' => $user,
            'activeMemberships' => $activeMemberships,
            'hasActivePackage' => $hasActivePackage,
            'qrCode' => $qrCode,
            'selectedMembershipId' => $this->selectedMembershipId,
            'selectedMembership' => $selectedMembership,
            'eligibleBookings' => $eligibleBookings,
            'selectedBookingId' => $this->selectedBookingId,
        ];
    }

    private function selectableMembershipQuery(): Builder
    {
        $user = $this->authenticatedUser();

        return Membership::query()
            ->where(function (Builder $query) use ($user): void {
                $query->whereBelongsTo($user, 'user')
                    ->orWhereHas('members', function (Builder $memberQuery) use ($user): void {
                        $memberQuery->whereKey($user->getKey());
                    });
            })
            ->where('status', 'active')
            ->where('is_active', true)
            ->where(function (Builder $query): void {
                $query->whereNull('start_date')
                    ->orWhereDate('start_date', '<=', today());
            });
    }

    private function eligibleBookingQuery(int $membershipId): Builder
    {
        return PtBooking::query()
            ->where('membership_id', $membershipId)
            ->where('member_id', $this->authenticatedUser()->getKey())
            ->where('status', 'approved')
            ->where('attendance', 'not_yet')
            ->whereNull('cancellation_requested_at');
    }

    private function validatedSelectableMembershipId(mixed $value): int
    {
        $membershipId = filter_var($value, FILTER_VALIDATE_INT);

        abort_unless(
            $membershipId !== false && $this->selectableMembershipQuery()->whereKey($membershipId)->exists(),
            403,
        );

        return $membershipId;
    }

    private function validatedEligibleBookingId(mixed $value): int
    {
        $bookingId = filter_var($value, FILTER_VALIDATE_INT);

        abort_unless($bookingId !== false && $this->selectedMembershipId !== null, 403);

        $membershipId = $this->validatedSelectableMembershipId($this->selectedMembershipId);

        abort_unless(
            $this->selectableMembershipQuery()
                ->whereKey($membershipId)
                ->where('type', 'pt')
                ->exists()
                && $this->eligibleBookingQuery($membershipId)->whereKey($bookingId)->exists(),
            403,
        );

        return $bookingId;
    }

    private function authenticatedUser(): User
    {
        $user = Auth::user();

        abort_unless($user instanceof User, 401);

        return $user;
    }
};
?>

<div class="member-check-in" wire:poll.2s="checkAttendance">
    <header class="checkin-brand">
        <div>
            <p class="checkin-wordmark" aria-label="Frans Gym">FRANS<span>GYM</span></p>
            <p class="checkin-tagline">NEVERBACKDOWN STAYDEDICATED</p>
        </div>
        <p class="checkin-motto" aria-hidden="true">DISCIPLINE<br>BUILDS<br>BETTER<br>PEOPLE</p>
    </header>

    <section class="checkin-card" aria-labelledby="checkin-title">
        <p class="checkin-instruction">Scan QR Code ini pada scanner admin untuk</p>
        <h1 id="checkin-title" class="checkin-title">CHECK-IN</h1>

        @if ($hasCheckedIn)
            <div class="checkin-message" role="status">
                <svg class="checkin-success-icon" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke-width="1.5"/><path d="m7 12 3 3 7-7" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                <h2>Berhasil!</h2>
                <p>Check-in kamu berhasil tercatat. Selamat berlatih di Frans Gym!</p>
                <button type="button" wire:click="$set('hasCheckedIn', false)" class="checkin-button">Kembali ke Kartu Member</button>
            </div>
        @elseif (! $hasActivePackage)
            <div class="checkin-message" role="status">
                <h2>Tidak Ada Paket Aktif</h2>
                <p>Anda belum memiliki paket membership atau masa aktif paket Anda telah habis. Silakan perpanjang atau beli paket baru untuk mendapatkan akses Check-in.</p>
            </div>
        @else
            <div class="checkin-package">
                <svg class="checkin-field-icon" aria-hidden="true" fill="currentColor" viewBox="0 0 24 24"><path d="M12 12a4.5 4.5 0 1 0 0-9 4.5 4.5 0 0 0 0 9Zm0 2c-5 0-8 2.6-8 6v1h16v-1c0-3.4-3-6-8-6Z"/></svg>
                @if ($activeMemberships->count() > 1)
                    <div class="checkin-package-choice">
                        <span aria-hidden="true" class="checkin-package-name">
                            {{ $selectedMembership?->type === 'pt' ? ($selectedMembership->ptPackage?->name ?? 'Paket PT') : ($selectedMembership?->gymPackage?->name ?? 'Paket '.ucfirst($selectedMembership?->type ?? '')) }}
                        </span>
                        <select wire:model.live="selectedMembershipId" aria-label="Pilih paket check-in">
                            @foreach ($activeMemberships as $membership)
                                <option wire:key="checkin-package-{{ $membership->id }}" value="{{ $membership->id }}">
                                    {{ $membership->type === 'pt' ? ($membership->ptPackage?->name ?? 'Paket PT') : ($membership->gymPackage?->name ?? 'Paket '.ucfirst($membership->type)) }}
                                </option>
                            @endforeach
                        </select>
                        <svg class="checkin-chevron" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="m6 9 6 6 6-6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </div>
                @elseif ($selectedMembership)
                    <span class="checkin-package-name">
                        {{ $selectedMembership->type === 'pt' ? ($selectedMembership->ptPackage?->name ?? 'Paket PT') : ($selectedMembership->gymPackage?->name ?? 'Paket '.ucfirst($selectedMembership->type)) }}
                    </span>
                @endif
            </div>

            @if ($selectedMembership && $selectedMembership->type === 'pt')
                <div class="checkin-booking">
                    <svg class="checkin-field-icon" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 11h18M7 15h2m2 0h2m2 0h2M7 18h2m2 0h2" stroke-linecap="round"/></svg>
                    <select wire:model.live="selectedBookingId" wire:key="checkin-bookings-{{ $selectedMembershipId }}" aria-label="Pilih jadwal booking" @disabled($eligibleBookings->isEmpty())>
                        <option value="">-- Pilih Jadwal Booking --</option>
                        @foreach ($eligibleBookings as $booking)
                            <option wire:key="checkin-booking-{{ $booking->id }}" value="{{ $booking->id }}">{{ $booking->booking_date->locale('id')->isoFormat('dddd, D MMM YYYY') }} - {{ $booking->booking_time->format('H:i') }}</option>
                        @endforeach
                    </select>
                    <svg class="checkin-chevron" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="m6 9 6 6 6-6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </div>
            @endif

            <div class="checkin-qr-panel" aria-live="polite">
                <div wire:loading wire:target="selectedMembershipId,selectedBookingId" role="status">Menyiapkan QR code...</div>
                <div wire:loading.remove wire:target="selectedMembershipId,selectedBookingId">
                    @if ($qrCode)
                        <div class="checkin-qr" role="img" aria-label="QR code check-in member">{!! $qrCode !!}</div>
                    @else
                        <svg class="checkin-placeholder" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="5" height="5" rx="1"/><rect x="16" y="3" width="5" height="5" rx="1"/><rect x="3" y="16" width="5" height="5" rx="1"/><path d="M12 3v1m0 5v4h5M3 12h5m4 5v4m5-4h4m-4 4h2m2-9h.01"/></svg>
                        <p>{{ $eligibleBookings->isEmpty() ? 'Silakan Booking Jadwal terlebih dahulu' : 'Pilih jadwal booking terlebih dahulu' }}</p>
                    @endif
                </div>
            </div>

            <footer class="checkin-member">
                <div class="checkin-member-name"><span aria-hidden="true"></span><h2>{{ $user->name }}</h2><span aria-hidden="true"></span></div>
                <p class="checkin-status"><span aria-hidden="true"></span>Status : Active</p>
            </footer>
        @endif
    </section>

    <footer class="checkin-footer" aria-hidden="true">
        <p>A<br>STRONGER<br>HEALTHIER<br>HAPPIER YOU</p>
        <p>FRANSGYM<br>JAMBI</p>
    </footer>
</div>
