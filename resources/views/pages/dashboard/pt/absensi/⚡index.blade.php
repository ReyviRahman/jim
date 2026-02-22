<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\Auth;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

new #[Layout('layouts::pt')] class extends Component
{
    public function with(): array
    {
        $user = Auth::user();
        
        // Merangkai data untuk QR Code Coach: user_id|none|trainer
        $qrData = $user->id . '|none|trainer';

        return [
            'user' => $user,
            'qrData' => $qrData,
        ];
    }
};
?>

<div class="max-w-md mx-auto mt-10">
    <div class="bg-white border border-default rounded-lg shadow-sm overflow-hidden">
        <div class="p-4 text-center bg-neutral-primary-soft">
            <p class="text-sm font-medium text-body mb-6">
                Tunjukkan QR Code ini ke Scanner Admin untuk absensi kehadiran kerja.
            </p>

            <div class="flex justify-center bg-white rounded-lg border border-default inline-block mx-auto shadow-sm">
                {!! QrCode::size(250)->style('round')->generate($qrData) !!}
            </div>
        </div>
    </div>
</div>