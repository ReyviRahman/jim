<?php
namespace App\Livewire\Admin;

use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use App\Models\Attendance;
use App\Models\Membership;
use App\Models\PtBooking;
use App\Models\User;
use Carbon\Carbon;

new #[Layout('layouts::admin')] class extends Component
{
    use WithPagination;

    public $scannedCode = '';

    public $dateStart = null;
    public $dateEnd = null;
    public $roleFilter = '';

public function processScan()
    {
        $data = json_decode($this->scannedCode, true);

        if (!$data || !isset($data['user_id'])) {
            session()->flash('error', 'Format QR Code tidak valid!');
            $this->scannedCode = '';
            return;
        }

        $userId = $data['user_id'];
        $membershipId = $data['membership_id'] ?? null;
        $bookingId = $data['booking_id'] ?? null;

        $user = User::find($userId);
        if (!$user) {
            session()->flash('error', 'Data Member tidak ditemukan!');
            $this->scannedCode = '';
            return;
        }

        $recentScan = Attendance::where('user_id', $userId)
            ->where('check_in_time', '>=', now()->subMinutes(1))
            ->first();

        if ($recentScan) {
            session()->flash('error', "Member {$user->name} Telah Melakukan absen");
            $this->scannedCode = '';
            return;
        }

        if ($user->role === 'pt') {
            Attendance::create([
                'user_id' => $user->id,
                'membership_id' => null,
                'type' => 'coach_attendance',
                'check_in_time' => now(),
            ]);

            session()->flash('success', "Berhasil Check-In Coach: {$user->name}. Selamat bertugas!");
            $this->scannedCode = '';
            return;
        }

        if ($bookingId && $membershipId) {
            $booking = PtBooking::with('membership')->find($bookingId);

            if (!$booking) {
                session()->flash('error', "Booking tidak ditemukan!");
                $this->scannedCode = '';
                return;
            }

            if ($booking->status !== 'approved') {
                session()->flash('error', "Booking tidak valid! Status: {$booking->status}");
                $this->scannedCode = '';
                return;
            }

            if ($booking->attendance === 'attended') {
                session()->flash('error', "Booking sudah pernah di-check-in!");
                $this->scannedCode = '';
                return;
            }

            if ($booking->attendance === 'noshow') {
                session()->flash('error', "Booking ini sudah hangus!");
                $this->scannedCode = '';
                return;
            }

            if ($booking->attendance === 'not_yet') {
                $membership = $booking->membership;

                if (!$booking->is_free && (!$membership || $membership->remaining_sessions <= 0)) {
                    session()->flash('error', 'Sesi Personal Trainer Anda sudah habis.');
                    $this->scannedCode = '';
                    return;
                }

                $booking->update(['attendance' => 'attended']);

                if (!$booking->is_free) {
                    $membership->decrement('remaining_sessions');

                    if ($membership->remaining_sessions == 0) {
                        $membership->update(['status' => 'completed']);
                    }
                }

                Attendance::create([
                    'user_id' => $user->id,
                    'membership_id' => $membership?->id,
                    'type' => 'pt',
                    'check_in_time' => now(),
                ]);

                $sessionInfo = $booking->is_free
                    ? 'Sesi PT Gratis'
                    : "Sesi PT - Sisa: {$membership->remaining_sessions} sesi";

                session()->flash('success', "Berhasil Check-In: {$user->name}. {$sessionInfo}.");
                $this->scannedCode = '';
                return;
            }
        }

        $membershipQuery = Membership::where(function ($query) use ($userId) {
                $query->where('user_id', $userId)
                    ->orWhereExists(function ($subQuery) use ($userId) {
                        $subQuery->select(DB::raw(1))
                                ->from('membership_users')
                                ->whereColumn('membership_users.membership_id', 'memberships.id')
                                ->where('membership_users.user_id', $userId);
                    });
            })
            ->where(function ($query) {
                $query->where('status', 'active')
                    ->orWhere(function ($q) {
                        $q->where('status', 'completed')
                            ->where('type', 'pt');
                    });
            });

        if ($membershipId) {
            $membership = $membershipQuery->where('id', $membershipId)->first();
        } else {
            $membership = $membershipQuery->orderByRaw("CASE WHEN status = 'active' THEN 1 WHEN status = 'completed' THEN 2 ELSE 3 END")->latest('id')->first();
        }

        if (!$membership) {
            session()->flash('error', "Akses Ditolak! Member {$user->name} tidak memiliki paket aktif.");
            $this->scannedCode = '';
            return;
        }

        $latestEndDate = null;
        if (in_array($membership->type, ['membership', 'bundle_pt_membership', 'visit'])) {
            $latestEndDate = Carbon::parse($membership->membership_end_date);
        } elseif ($membership->type === 'pt') {
            $latestEndDate = Carbon::parse($membership->pt_end_date);
        }

        if ($latestEndDate && now() > $latestEndDate->endOfDay()) {
            if ($membership->status !== 'completed') {
                $membership->update(['status' => 'completed']);
            }
            session()->flash('error', 'Gagal! Masa aktif paket sudah berakhir.');
            $this->scannedCode = '';
            return;
        }

        $attendanceType = 'gym';
        $infoSesi = "";

        if ($membership->type === 'pt') {
            session()->flash('error', "PT harus melalui booking! Gunakan QR dari halaman member.");
            $this->scannedCode = '';
            return;

        } elseif ($membership->type === 'visit') {

            $attendanceType = 'visit';
            $membership->update(['status' => 'completed']);
            $infoSesi = "Akses Visit Harian (Tiket telah digunakan).";

        } elseif ($membership->type === 'bundle_pt_membership') {

            $attendanceType = 'gym';
            $infoSesi = "Akses Gym (Bundle). Sisa PT: {$membership->remaining_sessions} (Sesi PT tidak dipotong otomatis via scanner).";

        } else {

            $attendanceType = 'gym';
            $infoSesi = "Akses Gym Mandiri (Berlaku s/d " . $latestEndDate->format('d M Y') . ")";

        }

        Attendance::create([
            'user_id' => $user->id,
            'membership_id' => $membership->id,
            'type' => $attendanceType,
            'check_in_time' => now(),
        ]);

session()->flash('success', "Berhasil Check-In: {$user->name}. {$infoSesi}");
        $this->scannedCode = '';
    }

    public function setDateRange($rangeStr)
    {
        if (str_contains($rangeStr, ' to ')) {
            $dates = explode(' to ', $rangeStr);
            $this->dateStart = $dates[0];
            $this->dateEnd = $dates[1];
        } elseif ($rangeStr) {
            $this->dateStart = $rangeStr;
            $this->dateEnd = $rangeStr;
        } else {
            $this->dateStart = null;
            $this->dateEnd = null;
        }

        $this->resetPage();
    }

    public function with(): array
    {
        $query = Attendance::with([
            'user',
            'membership.gymPackage',
            'membership.ptPackage',
            'membership.personalTrainer' // <--- Tambahkan baris ini
        ]);

        if ($this->dateStart && $this->dateEnd) {
            $query->whereBetween('check_in_time', [
                $this->dateStart.' 00:00:00',
                $this->dateEnd.' 23:59:59',
            ]);
        }

        if ($this->roleFilter === 'member') {
            $query->whereHas('user', function ($q) {
                $q->where('role', 'member');
            });
        } elseif ($this->roleFilter === 'employee') {
            $query->whereHas('user', function ($q) {
                $q->where('role', '!=', 'member');
            });
        }

        return [
            'attendances' => $query->latest('check_in_time')->paginate(10),
        ];
    }
};
?>

<div>
    <div class="flex justify-between items-center mb-6">
        <h5 class="text-xl font-semibold text-heading">Data Absensi & Scanner</h5>
        <div class="relative w-full max-w-md">
            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                <svg class="w-5 h-5 text-gray-400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 013.75 9.375v-4.5zM3.75 14.625c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5a1.125 1.125 0 01-1.125-1.125v-4.5zM13.5 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0113.5 9.375v-4.5z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 6.75h.75v.75h-.75v-.75zM6.75 16.5h.75v.75h-.75v-.75zM16.5 6.75h.75v.75h-.75v-.75zM13.5 13.5h.75v.75h-.75v-.75zM13.5 19.5h.75v.75h-.75v-.75zM19.5 13.5h.75v.75h-.75v-.75zM19.5 19.5h.75v.75h-.75v-.75zM16.5 16.5h.75v.75h-.75v-.75z" />
                </svg>
            </div>
            <input 
                type="text" 
                id="scanner_input" 
                wire:model="scannedCode" 
                wire:keydown.enter="processScan"
                class="bg-gray-50 border border-brand text-gray-900 text-sm rounded-lg focus:ring-brand focus:border-brand block w-full pl-10 p-3 shadow-sm" 
                placeholder="Hasil QR muncul disini" 
                autofocus
                autocomplete="off"
            >
        </div>
    </div>

    @if (session()->has('success'))
        <div class="mb-4 p-4 text-sm text-green-800 rounded-lg bg-green-50 border border-green-200" role="alert">
            <span class="font-medium">Berhasil!</span> {{ session('success') }}
        </div>
    @endif

    @if (session()->has('error'))
        <div class="mb-4 p-4 text-sm text-red-800 rounded-lg bg-red-50 border border-red-200" role="alert">
            <span class="font-medium">Gagal!</span> {{ session('error') }}
        </div>
    @endif

    <div class="mb-4 flex flex-col md:flex-row items-start md:items-center justify-between gap-3">
        <div class="flex flex-col sm:flex-row items-start sm:items-center gap-3 w-full md:w-auto">
            <div class="relative w-full sm:w-56" wire:ignore>
                <div class="absolute inset-y-0 start-0 flex items-center ps-3 pointer-events-none">
                    <svg class="w-4 h-4 text-body" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 10h16M8 14h8m-4-7V4M7 7V4m10 3V4M5 20h14a1 1 0 0 0 1-1V7a1 1 0 0 0-1-1H5a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1Z"/></svg>
                </div>
                <input type="text" x-data
                    x-init="flatpickr($el, {
                        mode: 'range',
                        dateFormat: 'Y-m-d',
                        placeholder: 'Pilih Rentang Tanggal',
                        onClose: function(selectedDates, dateStr, instance) {
                            @this.call('setDateRange', dateStr)
                        }
                    })"
                    class="block w-full ps-9 pe-3 py-2.5 bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base focus:ring-brand focus:border-brand shadow-xs placeholder:text-body"
                    placeholder="Pilih Rentang Tanggal">
            </div>

            <select wire:model.live="roleFilter"
                class="bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base focus:ring-brand focus:border-brand shadow-xs block w-full sm:w-40 ps-3 pe-8 py-2.5">
                <option value="">Semua Role</option>
                <option value="member">Member</option>
                <option value="employee">Karyawan</option>
            </select>
        </div>
    </div>

    <div class="relative overflow-x-auto bg-neutral-primary-soft shadow-xs rounded-md border border-default">
        <table class="w-full text-sm text-left rtl:text-right text-body">
            <thead class="text-sm text-body bg-neutral-secondary-medium border-b border-default-medium">
                <tr>
                    <th scope="col" class="px-6 py-3 font-medium">No</th>
                    <th scope="col" class="px-6 py-3 font-medium">Nama Member</th>
                    <th scope="col" class="px-6 py-3 font-medium">Tipe Kedatangan</th>
                    <th scope="col" class="px-6 py-3 font-medium">Detail Paket</th>
                    <th scope="col" class="px-6 py-3 font-medium">Waktu Check-In</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($attendances as $attendance)
                    <tr wire:key="{{ $attendance->id }}" class="bg-neutral-primary-soft border-b border-default hover:bg-neutral-secondary-medium">
                        <td class="px-7 py-4 font-medium text-heading whitespace-nowrap">
                            {{ $loop->iteration + ($attendances->currentPage() - 1) * $attendances->perPage() }}
                        </td>
                        
                        <td class="flex items-center px-6 py-4 font-medium text-heading whitespace-nowrap">
                            @if($attendance->user)
                                @if($attendance->user->photo)
                                    <img class="w-10 h-10 rounded-full object-cover mr-3 border border-gray-200" src="{{ asset('storage/' . $attendance->user->photo) }}" alt="{{ $attendance->user->name }}">
                                @else
                                    <img class="w-10 h-10 rounded-full object-cover mr-3 border border-gray-200" src="https://ui-avatars.com/api/?name={{ urlencode($attendance->user->name) }}&background=random" alt="{{ $attendance->user->name }}">
                                @endif
                                <div>
                                    <div class="font-semibold">{{ $attendance->user->name }}</div>
                                    <div class="text-xs text-gray-500 font-normal">{{ $attendance->user->email }}</div>
                                </div>
                            @else
                                <span class="text-red-500 italic">User Terhapus</span>
                            @endif
                        </td>

                        <td class="px-6 py-4 whitespace-nowrap">
                            @if($attendance->type === 'gym')
                                <span class="px-2.5 py-1 inline-flex text-xs leading-5 font-semibold rounded-md bg-emerald-100 text-emerald-800 border border-emerald-200">
                                    🏋️ Gym Mandiri
                                </span>
                            @elseif($attendance->type === 'pt')
                                <span class="px-2.5 py-1 inline-flex text-xs leading-5 font-semibold rounded-md bg-indigo-100 text-indigo-800 border border-indigo-200">
                                    👨‍🏫 Sesi PT
                                </span>
                            @elseif($attendance->type === 'visit')
                                <span class="px-2.5 py-1 inline-flex text-xs leading-5 font-semibold rounded-md bg-orange-100 text-orange-800 border border-orange-200">
                                    🎟️ Visit Harian
                                </span>
                            @elseif($attendance->type === 'coach_attendance')
                                <span class="px-2.5 py-1 inline-flex text-xs leading-5 font-semibold rounded-md bg-blue-100 text-blue-800 border border-blue-200">
                                    📋 Kehadiran Coach
                                </span>
                            @endif
                        </td>
                        
                        <td class="px-6 py-4 font-medium text-heading whitespace-nowrap">
                            @if($attendance->membership)
                                <div class="flex flex-col gap-1">
                                    @if(in_array($attendance->type, ['gym', 'visit']) && $attendance->membership->gymPackage)
                                        <div class="text-sm font-semibold text-emerald-700">
                                            {{ $attendance->membership->gymPackage->name }}
                                        </div>
                                    @endif

                                    @if($attendance->type === 'pt' && $attendance->membership->ptPackage)
                                        <div class="text-sm font-semibold text-indigo-700">
                                            {{ $attendance->membership->ptPackage->name }}
                                        </div>
                                        <div class="text-xs text-gray-600 mt-0.5">
                                            Coach: <span class="font-bold">{{ $attendance->membership->personalTrainer->name ?? '-' }}</span>
                                        </div>
                                    @endif
                                </div>
                            @else
                                <span class="text-red-500 italic">-</span>
                            @endif
                        </td>
                        
                        <td class="px-6 py-4 font-medium text-heading whitespace-nowrap">
                            <div class="flex items-center text-gray-600">
                                {{ \Carbon\Carbon::parse($attendance->check_in_time)->format('d M Y') }}
                                <span class="ml-2 font-bold text-gray-800">
                                    {{ \Carbon\Carbon::parse($attendance->check_in_time)->format('H:i') }}
                                </span>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-6 py-8 text-center text-gray-500">
                            Tidak ada data absensi.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    
    <div class="mt-4">
        {{ $attendances->links() }}
    </div>
</div>