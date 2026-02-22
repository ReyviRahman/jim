<?php

use Livewire\Component;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Carbon\Carbon; // Import Carbon untuk mengecek tanggal

new #[Layout('layouts::member')] class extends Component
{
    public function render()
    {
        // 1. Ambil User yang sedang login
        $user = Auth::user();
        
        // 2. Cek apakah user punya membership aktif
        $activeMembership = $user->activeMembership();

        // --- TAMBAHAN PENGECEKAN KEDALUWARSA ---
        // Jika punya membership aktif, tapi tanggal end_date sudah lewat hari ini
        if ($activeMembership && now()->startOfDay() > Carbon::parse($activeMembership->end_date)->startOfDay()) {
            // Otomatis ubah status di database menjadi completed
            $activeMembership->update(['status' => 'completed']);
            
            // Kosongkan variabel agar sistem menganggap dia tidak punya paket aktif
            $activeMembership = null; 
        }
        // ---------------------------------------

        // 3. Tentukan data absen dan status tampilan berdasarkan kepemilikan paket
        if ($activeMembership) {
            // Format: user_id | membership_id | tipe
            $dataAbsen = $user->id . '|' . $activeMembership->id . '|membership';
            $statusText = 'Membership Aktif (s/d ' . Carbon::parse($activeMembership->end_date)->format('d M Y') . ')';
            $statusColor = 'text-green-600 bg-green-100';
        } else {
            // Format: user_id | none | tipe
            $dataAbsen = $user->id . '|none|visit';
            $statusText = 'Visit Harian (Non-Member)';
            $statusColor = 'text-yellow-700 bg-yellow-100';
        }

        // 4. Generate QR Code (Format SVG lebih tajam)
        $qrCode = QrCode::size(200)
                    ->format('svg')         // Format SVG agar tajam
                    ->errorCorrection('H')  // High Error Correction (Lebih mudah dibaca scanner)
                    ->color(0, 0, 0)
                    ->backgroundColor(255, 255, 255)
                    ->margin(2)
                    ->generate($dataAbsen);

        // Kirim variable ke view
        return view('pages.dashboard.member.⚡home', [
            'qrCode' => $qrCode,
            'user' => $user,
            'statusText' => $statusText,
            'statusColor' => $statusColor,
            'activeMembership' => $activeMembership // Kirim data membership untuk info tambahan di view
        ]);
    }
};
?>

<div class="flex flex-col items-center justify-center min-h-[400px] p-6 bg-white rounded-xl shadow-lg border border-gray-100">
    
    <div class="text-center mb-6">
        <h2 class="text-2xl font-bold text-gray-800">Kartu Absensi Digital</h2>
        <p class="text-gray-500 text-sm mt-1">Scan QR Code ini pada alat absensi</p>
    </div>

    <div class="mb-5 text-center">
        <span class="px-3 py-1 text-sm font-semibold rounded-full {{ $statusColor }}">
            {{ $statusText }}
        </span>
        
        @if($activeMembership?->total_sessions)
            <p class="text-xs text-gray-500 font-medium mt-2">
                Sisa Sesi Bersama Coach: <span class="{{ $activeMembership->remaining_sessions <= 2 ? 'text-red-500' : 'text-green-600' }}">{{ $activeMembership->remaining_sessions }}</span> / {{ $activeMembership->total_sessions }}
            </p>
        @endif
    </div>

    <div class="p-4 bg-white border-2 border-dashed border-gray-300 rounded-lg shadow-sm transition-transform hover:scale-105 duration-300">
        @if(isset($qrCode))
            {!! $qrCode !!} 
        @else
            <p class="text-red-500">Silakan login untuk melihat QR.</p>
        @endif
    </div>

    @if(isset($user))
        <div class="mt-6 text-center">
            <p class="font-semibold text-xl text-gray-800">{{ $user->name }}</p>
        </div>
    @endif

</div>