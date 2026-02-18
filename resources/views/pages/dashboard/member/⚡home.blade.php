<?php

use Livewire\Component;
use SimpleSoftwareIO\QrCode\Facades\QrCode; // Import Library
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;

new #[Layout('layouts::member')] class extends Component
{
    public function render()
    {
        // 1. Ambil User yang sedang login
        $user = Auth::user();

        // 2. Tentukan data apa yang mau disimpan di QR
        // Bisa ID user, NIK, atau Token khusus.
        // Contoh: Mengirim string JSON berisi ID dan Email.
        // $dataAbsen = $user ? json_encode([
        //     'id' => $user->id,
        //     'email' => $user->email,
        //     'type' => 'absensi'
        // ]) : 'Guest';

        $dataAbsen = 'guest|guest|absensi';

        // 3. Generate QR Code (Format SVG lebih tajam)
        $qrCode = QrCode::size(200)
                    ->format('svg')         // Format SVG agar tajam
                    ->errorCorrection('H')  // High Error Correction (Lebih mudah dibaca scanner)
                    ->color(0, 0, 0)
                    ->backgroundColor(255, 255, 255)
                    ->margin(2)
                    ->generate($dataAbsen);

        // Kirim variable $qrCode ke view
        return view('pages.dashboard.member.⚡home', [
            'qrCode' => $qrCode,
            'user' => $user
        ]);
    }
};
?>

<div class="flex flex-col items-center justify-center min-h-[400px] p-6 bg-white rounded-xl shadow-lg border border-gray-100">
    
    <div class="text-center mb-6">
        <h2 class="text-2xl font-bold text-gray-800">Kartu Absensi Digital</h2>
        <p class="text-gray-500">Scan QR Code ini pada alat absensi</p>
    </div>

    <div class="p-4 bg-white border-2 border-dashed border-gray-300 rounded-lg shadow-sm">
        @if(isset($qrCode))
            {!! $qrCode !!} 
        @else
            <p class="text-red-500">Silakan login untuk melihat QR.</p>
        @endif
    </div>

    @if(isset($user))
        <div class="mt-6 text-center">
            <p class="font-semibold text-lg text-gray-800">{{ $user->name }}</p>
            <p class="text-sm text-gray-500">{{ $user->occupation ?? 'Member' }}</p>
        </div>
    @endif

</div>