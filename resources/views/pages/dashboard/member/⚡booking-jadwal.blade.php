<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use App\Models\Membership;
use App\Models\PtBooking;
use Illuminate\Support\Facades\Auth;

new #[Layout('layouts::member')] class extends Component
{
    use WithPagination;

    public $search = '';

    public $showBookingModal = false;
    public $bookingMembershipId = null;
    public $bookingDate = '';
    public $bookingTime = '';
    public $selectedScheduleDayId = null;

    public $showCancelModal = false;
    public $cancelBookingId = null;
    public $cancelReason = '';

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function openBookingModal()
    {
        $this->bookingMembershipId = null;
        $this->bookingDate = '';
        $this->bookingTime = '';
        $this->selectedScheduleDayId = null;
        $this->resetErrorBag();
        $this->showBookingModal = true;
    }

    public function closeBookingModal()
    {
        $this->showBookingModal = false;
        $this->bookingMembershipId = null;
        $this->bookingDate = '';
        $this->bookingTime = '';
        $this->selectedScheduleDayId = null;
    }

    public function updatedBookingMembershipId()
    {
        $this->bookingDate = '';
        $this->bookingTime = '';
        $this->selectedScheduleDayId = null;
    }

    public function getSelectedMembershipProperty()
    {
        if (! $this->bookingMembershipId) {
            return null;
        }

        return Membership::with(['ptSchedule.days'])->find($this->bookingMembershipId);
    }

    public function getIsKeepScheduleProperty()
    {
        $membership = $this->getSelectedMembershipProperty();

        return $membership && $membership->ptSchedule && $membership->ptSchedule->isKeep();
    }

    public function getScheduleDaysProperty()
    {
        $membership = $this->getSelectedMembershipProperty();

        if (! $membership || ! $membership->ptSchedule) {
            return collect();
        }

        return $membership->ptSchedule->days;
    }

    public function saveBooking()
    {
        $membership = $this->getSelectedMembershipProperty();
        $isKeep = $this->getIsKeepScheduleProperty();

        $rules = [
            'bookingMembershipId' => 'required|exists:memberships,id',
            'bookingDate' => 'required|date|after_or_equal:today',
        ];

        $messages = [
            'bookingMembershipId.required' => 'Pilih membership terlebih dahulu.',
            'bookingDate.required' => 'Tanggal booking wajib diisi.',
            'bookingDate.after_or_equal' => 'Tanggal booking tidak boleh di masa lalu.',
        ];

        if ($isKeep) {
            $rules['selectedScheduleDayId'] = 'required|exists:pt_schedule_days,id';
            $messages['selectedScheduleDayId.required'] = 'Pilih jadwal hari terlebih dahulu.';
        } else {
            $rules['bookingTime'] = 'required';
            $messages['bookingTime.required'] = 'Waktu booking wajib diisi.';
        }

        $this->validate($rules, $messages);

        if (! $membership) {
            $this->addError('booking', 'Membership tidak ditemukan.');
            return;
        }

        if ($membership->remaining_sessions <= 0) {
            $this->addError('booking', 'Sesi PT Anda sudah habis.');
            return;
        }

        if (! $membership->pt_id) {
            $this->addError('booking', 'Coach belum ditentukan untuk membership ini.');
            return;
        }

        if ($isKeep) {
            $scheduleDay = $membership->ptSchedule->days->firstWhere('id', $this->selectedScheduleDayId);
            if (! $scheduleDay) {
                $this->addError('booking', 'Jadwal hari tidak ditemukan.');
                return;
            }
            $this->bookingTime = $scheduleDay->time->format('H:i:s');
        }

        $exists = PtBooking::where('pt_id', $membership->pt_id)
            ->where('booking_date', $this->bookingDate)
            ->where('booking_time', $this->bookingTime)
            ->where('status', 'approved')
            ->exists();

        if ($exists) {
            $this->addError('booking', 'Jadwal ini sudah dibooking oleh member lain. Silakan pilih waktu lain.');
            return;
        }

        PtBooking::create([
            'membership_id' => $membership->id,
            'member_id' => Auth::id(),
            'pt_id' => $membership->pt_id,
            'booking_date' => $this->bookingDate,
            'booking_time' => $this->bookingTime,
            'status' => 'approved',
        ]);

        session()->flash('success', 'Booking sesi PT berhasil!');
        $this->closeBookingModal();
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

        $booking = PtBooking::where('member_id', Auth::id())
            ->where('id', $this->cancelBookingId)
            ->where('status', 'approved')
            ->whereNull('cancellation_requested_at')
            ->first();

        if (! $booking) {
            session()->flash('error', 'Booking tidak ditemukan atau sudah tidak dapat dibatalkan.');
            $this->closeCancelModal();
            return;
        }

        if ($booking->isAttended()) {
            session()->flash('error', 'Booking sudah diabsen, tidak bisa dibatalkan.');
            $this->closeCancelModal();
            return;
        }

        $booking->update([
            'cancelled_by' => Auth::id(),
            'cancellation_reason' => $this->cancelReason,
            'cancellation_requested_at' => now(),
        ]);

        $this->closeCancelModal();
        session()->flash('success', 'Permintaan pembatalan telah diajukan dan menunggu persetujuan admin.');
    }

    public function with(): array
    {
        $userId = Auth::id();

        $query = PtBooking::with(['membership.ptPackage', 'membership.personalTrainer', 'pt'])
            ->where('member_id', $userId)
            ->orderBy('booking_date', 'desc');

        if (! empty($this->search)) {
            $query->where(function ($q) {
                $q->whereHas('membership.ptPackage', function ($subQ) {
                    $subQ->where('name', 'like', '%'.$this->search.'%');
                })->orWhereHas('membership.personalTrainer', function ($subQ) {
                    $subQ->where('name', 'like', '%'.$this->search.'%');
                })->orWhereHas('pt', function ($subQ) {
                    $subQ->where('name', 'like', '%'.$this->search.'%');
                });
            });
        }

        $bookings = $query->paginate(10);

        $availableMemberships = Membership::with(['ptPackage', 'personalTrainer', 'ptSchedule.days'])
            ->whereNotNull('pt_package_id')
            ->where('status', '!=', 'completed')
            ->where(function ($q) use ($userId) {
                $q->where('user_id', $userId)
                  ->orWhereHas('members', function ($subQ) use ($userId) {
                      $subQ->where('users.id', $userId);
                  });
            })
            ->where('remaining_sessions', '>', 0)
            ->whereNotNull('pt_id')
            ->orderBy('start_date', 'desc')
            ->get();

        return [
            'bookings' => $bookings,
            'availableMemberships' => $availableMemberships,
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
            <p class="text-sm text-gray-500 mt-1">Daftar semua booking personal training Anda.</p>
        </div>

        <div class="flex items-center gap-3">
            @if($availableMemberships->count() > 0)
                <button type="button" wire:click="openBookingModal"
                    class="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors shadow-sm">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
                    Booking Baru
                </button>
            @endif
            <div class="relative w-72">
                <div class="absolute inset-y-0 start-0 flex items-center ps-3 pointer-events-none">
                    <svg class="w-4 h-4 text-gray-400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 20 20">
                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m19 19-4-4m0-7A7 7 0 1 1 1 8a7 7 0 0 1 14 0Z"/>
                    </svg>
                </div>
                <input 
                    type="text" 
                    wire:model.live.debounce.300ms="search" 
                    class="block w-full ps-10 pe-3 py-2.5 bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 shadow-sm placeholder:text-gray-400" 
                    placeholder="Cari paket atau coach...">
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left text-gray-600">
                <thead class="text-xs text-gray-700 uppercase bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th scope="col" class="px-6 py-4 font-semibold">No</th>
                        <th scope="col" class="px-6 py-4 font-semibold">Tanggal</th>
                        <th scope="col" class="px-6 py-4 font-semibold">Waktu</th>
                        <th scope="col" class="px-6 py-4 font-semibold">Paket</th>
                        <th scope="col" class="px-6 py-4 font-semibold">Coach</th>
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
                            <td class="px-6 py-4 whitespace-nowrap text-gray-700">
                                {{ $booking->booking_date?->locale('id')->isoFormat('dddd, D MMM YYYY') ?? '-' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-gray-700">
                                {{ $booking->booking_time?->format('H:i') ?? '-' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800">
                                    {{ $booking->membership?->ptPackage?->name ?? '-' }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="text-sm text-gray-700">{{ $booking->membership?->personalTrainer?->name ?? $booking->pt?->name ?? '-' }}</span>
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

                                @if($booking->status === 'cancelled' && $booking->cancellation_reason)
                                    <div class="mt-1 text-xs text-gray-500 max-w-xs">
                                        <span class="font-medium">Alasan:</span> <span class="italic">"{{ $booking->cancellation_reason }}"</span>
                                    </div>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                @if($booking->isCancellationPending())
                                    <span class="text-xs text-yellow-600 font-medium">Menunggu Approval</span>
                                @elseif($booking->isApproved() && $booking->isAttendanceNotYet())
                                    <button type="button" wire:click="openCancelModal({{ $booking->id }})"
                                        class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium text-red-700 bg-red-50 hover:bg-red-100 border border-red-200 rounded-md transition-colors">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                        Batalkan
                                    </button>
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
                                @if($search)
                                    <p class="text-xs text-gray-400 mt-1">Tidak ada booking dengan kata kunci "{{ $search }}".</p>
                                @else
                                    <p class="text-xs text-gray-400 mt-1">Booking akan muncul setelah Anda melakukan booking sesi PT.</p>
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

    {{-- Modal Booking --}}
    @if($showBookingModal)
        @php
            $selectedMembership = $this->getSelectedMembershipProperty();
            $isKeep = $this->getIsKeepScheduleProperty();
            $scheduleDays = $this->getScheduleDaysProperty();
        @endphp
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm" wire:click.self="closeBookingModal">
            <div class="bg-white rounded-lg shadow-xl w-full max-w-md mx-4 p-6" @click.stop>
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Booking Sesi PT</h3>
                    <button type="button" wire:click="closeBookingModal" class="text-gray-400 hover:text-gray-600">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>

                @if($errors->has('booking'))
                    <div class="mb-4 p-3 bg-red-100 border border-red-400 text-red-700 rounded text-sm">
                        {{ $errors->first('booking') }}
                    </div>
                @endif

                <form wire:submit.prevent="saveBooking" class="space-y-4">
                    <div>
                        <label for="bookingMembershipId" class="block text-sm font-medium text-gray-700 mb-1">Membership <span class="text-red-500">*</span></label>
                        <select id="bookingMembershipId" wire:model.live="bookingMembershipId"
                            class="block w-full px-3 py-2 bg-white border border-gray-300 rounded-md text-sm focus:ring-blue-500 focus:border-blue-500">
                            <option value="">Pilih membership</option>
                            @foreach($availableMemberships as $membership)
                                <option value="{{ $membership->id }}">
                                    {{ $membership->ptPackage?->name ?? '-' }} - Coach {{ $membership->personalTrainer?->name ?? '-' }} (Sisa: {{ $membership->remaining_sessions }} sesi)
                                </option>
                            @endforeach
                        </select>
                        @error('bookingMembershipId') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                    </div>

                    @if($selectedMembership)
                        <div class="bg-gray-50 p-3 rounded-md space-y-1">
                            <div class="flex justify-between">
                                <span class="text-xs text-gray-500">Tipe Jadwal</span>
                                <span class="text-sm font-medium text-gray-800 capitalize">{{ $selectedMembership->ptSchedule?->type ?? 'Fleksibel' }}</span>
                            </div>
                            @if($isKeep && $scheduleDays->count() > 0)
                                <div class="flex justify-between">
                                    <span class="text-xs text-gray-500">Jadwal Tersedia</span>
                                    <div class="text-sm text-gray-800 text-right">
                                        @foreach($scheduleDays as $day)
                                            <div>{{ ucfirst($day->day) }} - {{ $day->time->format('H:i') }}</div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endif

                    <div>
                        <label for="bookingDate" class="block text-sm font-medium text-gray-700 mb-1">Tanggal <span class="text-red-500">*</span></label>
                        <input type="date" id="bookingDate" wire:model.live="bookingDate" min="{{ now()->format('Y-m-d') }}"
                            class="block w-full px-3 py-2 bg-white border border-gray-300 rounded-md text-sm focus:ring-blue-500 focus:border-blue-500">
                        @if($bookingDate)
                            <p class="mt-1 text-sm text-blue-600 font-medium">
                                {{ \Carbon\Carbon::parse($bookingDate)->locale('id')->isoFormat('dddd, D MMMM YYYY') }}
                            </p>
                        @endif
                        @error('bookingDate') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                    </div>

                    @if($isKeep)
                        <div>
                            <label for="selectedScheduleDayId" class="block text-sm font-medium text-gray-700 mb-1">Pilih Jadwal Hari <span class="text-red-500">*</span></label>
                            <select id="selectedScheduleDayId" wire:model="selectedScheduleDayId"
                                class="block w-full px-3 py-2 bg-white border border-gray-300 rounded-md text-sm focus:ring-blue-500 focus:border-blue-500">
                                <option value="">Pilih hari dan waktu</option>
                                @foreach($scheduleDays as $day)
                                    <option value="{{ $day->id }}">{{ ucfirst($day->day) }} - {{ $day->time->format('H:i') }}</option>
                                @endforeach
                            </select>
                            @error('selectedScheduleDayId') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                        </div>
                    @else
                        <div>
                            <label for="bookingTime" class="block text-sm font-medium text-gray-700 mb-1">Waktu <span class="text-red-500">*</span></label>
                            <input type="time" id="bookingTime" wire:model="bookingTime"
                                class="block w-full px-3 py-2 bg-white border border-gray-300 rounded-md text-sm focus:ring-blue-500 focus:border-blue-500">
                            @error('bookingTime') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                        </div>
                    @endif

                    <div class="flex gap-3 pt-2">
                        <button type="button" wire:click="closeBookingModal"
                            class="flex-1 px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200">
                            Batal
                        </button>
                        <button type="submit" wire:loading.attr="disabled"
                            class="flex-1 px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 disabled:opacity-50">
                            <span wire:loading.remove wire:target="saveBooking">Booking Sekarang</span>
                            <span wire:loading wire:target="saveBooking">Menyimpan...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- Modal Cancel Reason --}}
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
                            placeholder="Silakan isi alasan mengapa Anda ingin membatalkan booking ini..."></textarea>
                        @error('cancelReason') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                    </div>

                    <div class="flex gap-3 pt-2">
                        <button type="button" wire:click="closeCancelModal"
                            class="flex-1 px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200">
                            Batal
                        </button>
                        <button type="submit" wire:loading.attr="disabled"
                            class="flex-1 px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-md hover:bg-red-700 disabled:opacity-50">
                            <span wire:loading.remove wire:target="cancelBooking">Ajukan Pembatalan</span>
                            <span wire:loading wire:target="cancelBooking">Menyimpan...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
