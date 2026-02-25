<?php

namespace App\Livewire\Admin; // Sesuaikan dengan namespace Anda

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use App\Models\Attendance;
use App\Models\Membership;
use App\Models\User;
use Carbon\Carbon;

new #[Layout('layouts::admin')] class extends Component
{
    use WithPagination;

    public $scannedCode = '';

    public function processScan()
    {
        // 1. Decode JSON dari QR Code (Format: {"user_id": 1})
        $data = json_decode($this->scannedCode, true);

        // Validasi format QR Code
        if (!$data || !isset($data['user_id'])) {
            session()->flash('error', 'Format QR Code tidak valid!');
            $this->scannedCode = ''; 
            return;
        }

        $userId = $data['user_id'];

        // 2. Cari Data User
        $user = User::find($userId);
        if (!$user) {
            session()->flash('error', 'Data Member tidak ditemukan!');
            $this->scannedCode = '';
            return;
        }

        // 3. Cegah double scan dalam waktu 1 menit terakhir
        $recentScan = Attendance::where('user_id', $userId)
            ->where('check_in_time', '>=', now()->subMinutes(1))
            ->first();

        if ($recentScan) {
            session()->flash('error', "Member {$user->name} Telah Melakukan absen");
            $this->scannedCode = '';
            return;
        }

        // 4. Cari Data Membership Aktif milik User tersebut
        // Menggunakan logika yang sama seperti di halaman user (cek owner atau member group)
        $membership = Membership::where('status', 'active')
            ->where(function ($query) use ($userId) {
                $query->where('user_id', $userId)
                      ->orWhereHas('users', function ($q) use ($userId) {
                          $q->where('users.id', $userId);
                      });
            })
            ->first();

        if (!$membership) {
            session()->flash('error', "Akses Ditolak! Member {$user->name} tidak memiliki paket aktif.");
            $this->scannedCode = '';
            return;
        }

        // 5. Validasi Kedaluwarsa Tanggal
        $latestEndDate = null;
        if (in_array($membership->type, ['membership', 'bundle_pt_membership', 'visit'])) {
            $latestEndDate = Carbon::parse($membership->membership_end_date);
        } elseif ($membership->type === 'pt') {
            $latestEndDate = Carbon::parse($membership->pt_end_date);
        }

        if ($latestEndDate && now() > $latestEndDate->endOfDay()) {
            $membership->update(['status' => 'completed']); 
            session()->flash('error', 'Gagal! Masa aktif paket sudah berakhir.');
            $this->scannedCode = '';
            return;
        }

        // 6. Tentukan Tipe Absensi dan Logika Pemotongan Sesi
        // Tipe absensi di tabel: 'gym', 'pt', 'visit'
        $attendanceType = 'gym'; 
        $infoSesi = "";

        if ($membership->type === 'pt') {
            
            $attendanceType = 'pt';
            if ($membership->remaining_sessions > 0) {
                $membership->decrement('remaining_sessions');
                
                // Jika ini sesi terakhir, ubah jadi completed
                if ($membership->remaining_sessions == 0) {
                    $membership->update(['status' => 'completed']);
                }
                $infoSesi = "[Sesi PT Digunakan] Sisa: {$membership->remaining_sessions} sesi.";
            } else {
                session()->flash('error', 'Gagal! Sesi Personal Trainer Anda sudah habis.');
                $this->scannedCode = '';
                return;
            }

        } elseif ($membership->type === 'visit') {
            
            // --- PERBAIKAN LOGIKA VISIT DI SINI ---
            $attendanceType = 'visit';
            
            // Karena visit hanya sekali pakai, kita langsung ubah statusnya jadi completed
            // setelah dia berhasil check-in hari ini.
            $membership->update(['status' => 'completed']);
            
            $infoSesi = "Akses Visit Harian (Tiket telah digunakan).";

        } elseif ($membership->type === 'bundle_pt_membership') {
            
            // Catatan: Karena scan otomatis tidak bisa menebak user datang untuk Gym atau PT,
            // kita set default check-in sebagai 'gym'. Jika dia ingin PT, kuota tidak terpotong otomatis di sini.
            $attendanceType = 'gym';
            $infoSesi = "Akses Gym (Bundle). Sisa PT: {$membership->remaining_sessions} (Sesi PT tidak dipotong otomatis via scanner).";

        } else {
            
            // Paket Gym Mandiri Biasa ('membership')
            $attendanceType = 'gym';
            $infoSesi = "Akses Gym Mandiri (Berlaku s/d " . $latestEndDate->format('d M Y') . ")";
            
        }

        // 7. Catat Absensi ke Database
        Attendance::create([
            'user_id' => $user->id,
            'membership_id' => $membership->id,
            'type' => $attendanceType,
            'check_in_time' => now(),
        ]);

        session()->flash('success', "Berhasil Check-In: {$user->name}. {$infoSesi}");
        $this->scannedCode = ''; // Bersihkan input
    }

    public function with(): array
    {
        return [
            'attendances' => Attendance::with(['user', 'membership.gymPackage', 'membership.ptPackage'])
                ->latest('check_in_time')
                ->paginate(10),
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
                            {{-- Disesuaikan dengan enum ['gym', 'pt', 'visit'] --}}
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
                            Belum ada data absensi hari ini.
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