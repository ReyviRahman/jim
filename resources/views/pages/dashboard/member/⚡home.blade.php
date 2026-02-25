<?php

namespace App\Livewire\Member; // Sesuaikan jika berbeda

use Livewire\Component;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Carbon\Carbon;

new #[Layout('layouts::member')] class extends Component
{
    public function render()
    {
        // 1. Ambil User yang sedang login
        $user = Auth::user();
        
        // 2. Cek apakah user punya membership aktif
        // (Pastikan fungsi activeMembership() di model User sudah mengambil data yang statusnya 'active')
        $activeMembership = $user->activeMembership();

        // 3. Logika Pengecekan Kedaluwarsa yang Baru
        if ($activeMembership) {
            $latestEndDate = null;

            // Tentukan tanggal mana yang dipakai sebagai patokan expired (gate access)
            if (in_array($activeMembership->type, ['membership', 'bundle_pt_membership', 'visit'])) {
                $latestEndDate = Carbon::parse($activeMembership->membership_end_date);
            } elseif ($activeMembership->type === 'pt') {
                $latestEndDate = Carbon::parse($activeMembership->pt_end_date);
            }

            // Jika tanggal hari ini sudah melewati tanggal kedaluwarsa
            if ($latestEndDate && now()->startOfDay() > $latestEndDate->startOfDay()) {
                // Otomatis ubah status di database menjadi expired
                $activeMembership->update(['status' => 'expired']);
                
                // Kosongkan variabel agar sistem menganggap dia tidak punya paket aktif
                $activeMembership = null; 
            }
        }

        // 4. Tentukan data absen dan status tampilan berdasarkan kepemilikan paket
        if ($activeMembership) {
            // Format: user_id | membership_id | tipe_paket
            $dataAbsen = $user->id . '|' . $activeMembership->id . '|' . $activeMembership->type;
            
            // Atur teks status berdasarkan tipe
            if ($activeMembership->type === 'visit') {
                $statusText = 'Visit Harian (Berlaku Hari Ini)';
            } elseif ($activeMembership->type === 'pt') {
                $statusText = 'Paket PT Aktif (s/d ' . Carbon::parse($activeMembership->pt_end_date)->format('d M Y') . ')';
            } else {
                $statusText = 'Membership Aktif (s/d ' . Carbon::parse($activeMembership->membership_end_date)->format('d M Y') . ')';
            }
            
            $statusColor = 'text-green-700 bg-green-100 border border-green-200';
        } else {
            // Format: user_id | none | none (Artinya ditolak di pintu masuk)
            $dataAbsen = $user->id . '|none|none';
            $statusText = 'Belum Ada Paket Aktif';
            $statusColor = 'text-red-700 bg-red-100 border border-red-200';
        }

        // 5. Generate QR Code (Format SVG lebih tajam)
        $qrCode = QrCode::size(200)
                    ->format('svg')         
                    ->errorCorrection('H')  
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
            'activeMembership' => $activeMembership 
        ]);
    }
};
?>

<div class="flex flex-col items-center justify-center min-h-[400px] p-6 bg-white rounded-xl shadow-lg border border-gray-100">
    
    <div class="text-center mb-6">
        <h2 class="text-2xl font-bold text-gray-800">Kartu Absensi Digital</h2>
        <p class="text-gray-500 text-sm mt-1">Scan QR Code ini pada alat absensi</p>
    </div>

    <div class="mb-5 text-center flex flex-col items-center">
        <span class="px-3 py-1.5 text-sm font-semibold rounded-full shadow-sm {{ $statusColor }}">
            {{ $statusText }}
        </span>
        
        {{-- Tampilkan sisa sesi JIKA paketnya punya sesi PT dan belum habis --}}
        @if($activeMembership?->total_sessions)
            <div class="mt-3 px-3 py-2 bg-neutral-50 rounded-lg border border-neutral-200">
                <p class="text-xs text-gray-600 font-medium">
                    Sisa Sesi Bersama Coach: 
                    <span class="font-bold text-base {{ $activeMembership->remaining_sessions <= 2 ? 'text-red-600' : 'text-indigo-600' }}">
                        {{ $activeMembership->remaining_sessions }}
                    </span> 
                    <span class="text-gray-400">/ {{ $activeMembership->total_sessions }}</span>
                </p>
            </div>
        @endif
    </div>

    <div class="p-4 bg-white border-2 border-dashed {{ $activeMembership ? 'border-brand-medium' : 'border-red-300' }} rounded-lg shadow-sm transition-transform hover:scale-105 duration-300 relative">
        @if(!$activeMembership)
            {{-- Beri overlay semi-transparan jika tidak aktif agar QR terkesan 'terkunci' --}}
            <div class="absolute inset-0 bg-white/60 z-10 flex items-center justify-center rounded-lg backdrop-blur-[1px]">
                <span class="bg-red-600 text-white text-xs font-bold px-2 py-1 rounded shadow-md transform -rotate-12">INACTIVE</span>
            </div>
        @endif
        
        @if(isset($qrCode))
            {!! $qrCode !!} 
        @else
            <p class="text-red-500">Silakan login untuk melihat QR.</p>
        @endif
    </div>

    @if(isset($user))
        <div class="mt-6 text-center">
            <p class="font-semibold text-xl text-gray-800">{{ $user->name }}</p>
            <p class="text-sm text-gray-500">{{ $user->email }}</p>
        </div>
    @endif

</div>