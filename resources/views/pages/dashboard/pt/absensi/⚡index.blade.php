<?php
namespace App\Livewire\Pt;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\Auth;
use App\Models\Attendance;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

new #[Layout('layouts::pt')] class extends Component
{
    public function with(): array
    {
        $user = Auth::user();
        
        // PERBAIKAN: Cek apakah coach BARU SAJA absen (dalam 15 detik terakhir)
        $justAttended = Attendance::where('user_id', $user->id)
            ->where('type', 'coach_attendance') 
            ->where('check_in_time', '>=', now()->subSeconds(15)) // Waktu notif tampil = 15 detik
            ->exists();

        $qrData = json_encode([
            'user_id' => $user->id
        ]);

        return [
            'user' => $user,
            'qrData' => $qrData,
            'justAttended' => $justAttended, // Kirim status ini ke view
        ];
    }
};
?>

<div class="max-w-md mx-auto mt-10" wire:poll.2s>
    <div class="bg-white border border-default rounded-lg shadow-sm overflow-hidden">
        <div class="p-4 text-center bg-neutral-primary-soft">
            
            @if($justAttended)
                <div class="py-8 transition-all duration-500">
                    <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-green-100 mb-4 shadow-sm border border-green-200">
                        <svg class="h-8 w-8 text-green-600" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                    </div>
                    <h2 class="text-xl font-bold text-green-800 mb-2">Absensi Berhasil!</h2>
                    <p class="text-sm font-medium text-green-700">
                        Kehadiran tercatat.
                    </p>
                </div>
            @else
                <h2 class="text-xl font-bold text-heading mb-2">QR Absensi Coach</h2>
                <p class="text-sm font-medium text-body mb-6">
                    Tunjukkan QR Code ini ke Scanner Admin untuk absensi kehadiran kerja.
                </p>

                <div class="flex justify-center bg-white p-4 rounded-lg border border-default inline-block mx-auto shadow-sm">
                    {!! QrCode::size(250)->style('round')->generate($qrData) !!}
                </div>
                
                <div class="mt-4 text-sm font-semibold text-gray-700">
                    {{ $user->name }}
                </div>
                <div class="text-xs text-gray-400 mt-1">
                    {"user_id": {{ $user->id }}}
                </div>
            @endif

        </div>
    </div>
</div>