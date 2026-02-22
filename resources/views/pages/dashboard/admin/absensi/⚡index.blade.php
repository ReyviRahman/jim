<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use App\Models\Attendance;
use App\Models\Membership;
use App\Models\User;

new #[Layout('layouts::admin')] class extends Component
{
    use WithPagination;

    // Properti untuk menampung hasil ketikan dari scanner QR
    public $scannedCode = '';

    public function processScan()
    {
        // 1. Pecah data QR Code (Format: user_id|membership_id|type)
        $data = explode('|', $this->scannedCode);

        // Validasi format QR
        if (count($data) !== 3) {
            session()->flash('error', 'Format QR Code tidak valid!');
            $this->scannedCode = ''; 
            return;
        }

        [$userId, $membershipId, $type] = $data;

        // 2. Cari Data User
        $user = User::find($userId);
        if (!$user) {
            session()->flash('error', 'Data Member tidak ditemukan!');
            $this->scannedCode = '';
            return;
        }

        // (Opsional) 3. Cegah double scan dalam waktu 1 menit terakhir
        $recentScan = Attendance::where('user_id', $userId)
            ->where('check_in_time', '>=', now()->subMinutes(1))
            ->first();

        if ($recentScan) {
            session()->flash('error', "Member {$user->name} baru saja melakukan scan beberapa saat yang lalu!");
            $this->scannedCode = '';
            return;
        }

        // 4. Proses berdasarkan Tipe (Membership atau Visit)
        if ($type === 'trainer') {
            Attendance::create([
                'user_id' => $user->id,
                'membership_id' => null, // Coach tidak butuh ID membership
                'type' => 'trainer',
                'check_in_time' => now(),
            ]);

            session()->flash('success', "Absensi Coach Berhasil: {$user->name}");
        }

        elseif ($type === 'membership' && $membershipId !== 'none') {
            $membership = Membership::find($membershipId);
            
            // --- BAGIAN INI YANG DIPERBARUI ---
            // Validasi Kedaluwarsa berdasarkan end_date
            if (!$membership || now()->startOfDay() > \Carbon\Carbon::parse($membership->end_date)->startOfDay()) {
                // Otomatis update ke completed jika lewat tanggal
                if ($membership && $membership->status === 'active') {
                    $membership->update(['status' => 'completed']); 
                }
                session()->flash('error', 'Gagal! Masa aktif membership sudah berakhir.');
                $this->scannedCode = '';
                return;
            }

            // Validasi Status Aktif
            if ($membership->status !== 'active') {
                session()->flash('error', 'Membership tidak valid atau belum diaktifkan!');
                $this->scannedCode = '';
                return;
            }

            // Logika Sesi Coach vs Gym Mandiri
            $infoSesi = "";
            if ($membership->total_sessions !== null) {
                // Jika Paket Coach
                if ($membership->remaining_sessions > 0) {
                    $membership->decrement('remaining_sessions');
                    $membership->refresh(); 
                    $infoSesi = "Sesi Coach Dipakai. Sisa Sesi PT: {$membership->remaining_sessions}";
                } else {
                    $infoSesi = "Sesi PT Habis. Masuk sebagai Gym Mandiri.";
                }
            } else {
                // Jika Paket Gym Mandiri (Unlimited Sesi)
                $infoSesi = "Paket Gym Mandiri (Berlaku s/d " . \Carbon\Carbon::parse($membership->end_date)->format('d M Y') . ")";
            }

            // Catat Absensi Membership
            Attendance::create([
                'user_id' => $user->id,
                'membership_id' => $membership->id,
                'type' => 'membership',
                'check_in_time' => now(),
            ]);

            session()->flash('success', "Berhasil Check-In: {$user->name}. {$infoSesi}");

        } else {
            // Catat Absensi Visit (Harian/Non-Paket)
            Attendance::create([
                'user_id' => $user->id,
                'membership_id' => null,
                'type' => 'visit',
                'check_in_time' => now(),
            ]);

            session()->flash('success', "Berhasil Check-In Visit: {$user->name}");
        }

        // Bersihkan inputan untuk scan berikutnya
        $this->scannedCode = '';
    }

    public function with(): array
    {
        return [
            // Ambil data absensi beserta relasi user dan paket gym-nya
            'attendances' => Attendance::with(['user', 'membership.gymPackage'])
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
                placeholder="Hasil scan akan muncul di sini..." 
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
                    <th scope="col" class="px-6 py-3 font-medium">Paket Gym</th>
                    <th scope="col" class="px-6 py-3 font-medium">Waktu Check-In</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($attendances as $attendance)
                    <tr wire:key="{{ $attendance->id }}" class="bg-neutral-primary-soft border-b border-default hover:bg-neutral-secondary-medium">
                        <td scope="row" class="px-7 py-4 font-medium text-heading whitespace-nowrap">
                            {{ $loop->iteration + ($attendances->currentPage() - 1) * $attendances->perPage() }}
                        </td>
                        <td class="flex items-center px-6 py-4 font-medium text-heading whitespace-nowrap">
                            @if($attendance->user)
                                @if($attendance->user->photo)
                                    <img class="w-10 h-10 rounded-full object-cover mr-3" src="{{ asset('storage/' . $attendance->user->photo) }}" alt="{{ $attendance->user->name }}">
                                @else
                                    <img class="w-10 h-10 rounded-full object-cover mr-3" src="https://ui-avatars.com/api/?name={{ urlencode($attendance->user->name) }}&background=random" alt="{{ $attendance->user->name }}">
                                @endif
                                
                                <span>{{ $attendance->user->name }}</span>
                            @else
                                <span>N/A</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @if($attendance->type === 'membership')
                                <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                    Membership
                                </span>
                            @elseif($attendance->type === 'trainer')
                                <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800 border border-blue-200">
                                    Coach / PT
                                </span>
                            @else
                                <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                    Visit Harian
                                </span>
                            @endif
                        </td>
                        <td class="px-6 py-4 font-medium text-heading whitespace-nowrap">
                            @if($attendance->type === 'membership' && $attendance->membership)
                                {{ $attendance->membership->gymPackage->name ?? 'Paket Tidak Ditemukan' }}
                            @else
                                -
                            @endif
                        </td>
                        <td class="px-6 py-4 font-medium text-heading whitespace-nowrap">
                            {{ $attendance->check_in_time->format('d M Y, H:i') }} WIB
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-6 py-4 text-center text-gray-500">
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