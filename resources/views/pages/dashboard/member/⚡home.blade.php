<?php

namespace App\Livewire\Member;

use Livewire\Component;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB; // WAJIB DI-IMPORT UNTUK QUERY OPTIMASI
use Livewire\Attributes\Layout;
use App\Models\Membership; 
use App\Models\Attendance; 
use Carbon\Carbon;

new #[Layout('layouts::member')] class extends Component
{
    public function with(): array
    {
        $user = Auth::user();
        
        // 1. Ambil paket 'active', ATAU paket 'completed' khusus PT
        // OPTIMASI: Menggunakan orWhereExists agar super cepat tanpa JOIN ke tabel users
        $rawActiveMemberships = Membership::with(['gymPackage', 'ptPackage'])
            ->where(function ($query) use ($user) {
                $query->where('user_id', $user->id)
                      ->orWhereExists(function ($subQuery) use ($user) {
                          $subQuery->select(DB::raw(1))
                                   ->from('membership_users')
                                   ->whereColumn('membership_users.membership_id', 'memberships.id')
                                   ->where('membership_users.user_id', $user->id);
                      });
            })
            ->where(function($query) {
                 $query->where('status', 'active')
                       ->orWhere(function($q) {
                           $q->where('status', 'completed')
                             ->where('type', 'pt');
                       });
            })
            ->get();

        // 2. Siapkan collection baru untuk menyimpan paket yang valid tayang
        $activeMemberships = collect();

        // 3. Lakukan pengecekan tanggal & sesi untuk setiap paket
        foreach ($rawActiveMemberships as $membership) {
            $isExpired = false;

            if ($membership->type === 'pt') {
                // Cek kadaluarsa PT 
                if ($membership->pt_end_date && Carbon::parse($membership->pt_end_date)->endOfDay()->isPast()) {
                    $isExpired = true;
                }
                
                // Cek sisa sesi PT
                if (!is_null($membership->remaining_sessions) && $membership->remaining_sessions <= 0) {
                    
                    // OPTIMASI: Menghindari whereDate() agar Index Database tetap bekerja
                    $isUsedToday = Attendance::where('membership_id', $membership->id)
                        ->where('type', 'pt')
                        ->where('check_in_time', '>=', today()->startOfDay())
                        ->where('check_in_time', '<=', today()->endOfDay())
                        ->exists();

                    if (!$isUsedToday) {
                        $isExpired = true;
                    }
                }
            } else {
                // Cek kadaluarsa Gym/Lainnya
                if ($membership->membership_end_date && Carbon::parse($membership->membership_end_date)->endOfDay()->isPast()) {
                    $isExpired = true;
                }
            }

            // 4. Update ke completed jika expired
            if ($isExpired) {
                if ($membership->status !== 'completed') {
                    $membership->update(['status' => 'completed']);
                }
            } else {
                $activeMemberships->push($membership);
            }
        }

        $hasActivePackage = $activeMemberships->isNotEmpty();
        $qrCode = null;

        // Hanya generate QR Code jika punya minimal 1 paket yang valid
        if ($hasActivePackage) {
            $qrData = json_encode(['user_id' => $user->id]);
            $qrCode = QrCode::size(220)->margin(1)->generate($qrData);
        }

        return [
            'user' => $user,
            'activeMemberships' => $activeMemberships, 
            'hasActivePackage' => $hasActivePackage,
            'qrCode' => $qrCode,
        ];
    }
};
?>

<div>
    <div class="max-w-lg mx-auto py-8 px-4 sm:px-6">
        
        @if(!$hasActivePackage)
            <div class="bg-white border border-red-100 rounded-3xl p-8 shadow-lg text-center relative overflow-hidden">
                <div class="absolute top-0 left-0 w-full h-2 bg-red-500"></div>
                <div class="bg-red-50 w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-6">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>
                <h2 class="text-2xl font-bold text-gray-800 mb-3">Tidak Ada Paket Aktif</h2>
                <p class="text-gray-500 text-sm leading-relaxed mb-6">Anda belum memiliki paket membership atau masa aktif paket Anda telah habis. Silakan perpanjang atau beli paket baru untuk mendapatkan akses Check-in.</p>
            </div>
        @else
            <div class="bg-white rounded-3xl shadow-xl overflow-hidden border border-gray-100">
                
                <div class="p-8 text-center bg-white border-b border-gray-100">
                    <p class="text-gray-500 text-sm mb-6 font-medium">Scan QR Code ini pada scanner admin untuk Check-in</p>
                    
                    <div class="inline-block p-4 bg-white rounded-2xl shadow-sm border border-gray-200 transition-transform hover:scale-105 duration-300 mb-6">
                        {!! $qrCode !!}
                    </div>

                    <div>
                        <h3 class="text-gray-800 text-2xl font-bold tracking-tight">{{ $user->name }}</h3>
                        <div class="mt-2 inline-flex items-center px-3 py-1 rounded-full bg-green-50 border border-green-200 text-green-700 text-xs font-bold">
                            <span class="w-2 h-2 rounded-full bg-green-500 mr-2 animate-pulse"></span>
                            Status: Active
                        </div>
                    </div>
                </div>

                <div class="p-6 bg-gray-50">
                    <h4 class="text-sm font-bold text-gray-400 uppercase tracking-wider mb-4">Paket Aktif Anda</h4>
                    
                    <div class="space-y-4">
                        @foreach($activeMemberships as $membership)
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between p-4 rounded-2xl bg-white border border-gray-100 shadow-sm hover:border-blue-200 hover:shadow transition duration-200">
                                
                                <div class="mb-4 sm:mb-0">
                                    <div class="flex items-center gap-2 mb-1">
                                        @if($membership->type === 'pt')
                                            <div class="bg-purple-100 text-purple-600 p-1.5 rounded-lg">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                            </div>
                                        @else
                                            <div class="bg-blue-100 text-blue-600 p-1.5 rounded-lg">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                                            </div>
                                        @endif

                                        <h5 class="font-bold text-gray-800">
                                            @if($membership->type === 'pt' && $membership->ptPackage)
                                                {{ $membership->ptPackage->name }}
                                            @elseif($membership->gymPackage)
                                                {{ $membership->gymPackage->name }}
                                            @else
                                                Paket Kustom
                                            @endif
                                        </h5>
                                    </div>
                                    <span class="text-xs font-semibold px-2 py-0.5 rounded-md bg-gray-100 text-gray-600 uppercase tracking-wide">
                                        {{ str_replace('_', ' ', $membership->type) }}
                                    </span>
                                </div>

                                <div class="text-left sm:text-right space-y-2">
                                    
                                    {{-- JIKA PAKET BERUPA PT --}}
                                    @if($membership->type === 'pt')
                                        @if($membership->pt_end_date)
                                            <div class="text-xs text-gray-500 flex flex-col sm:items-end gap-1">
                                                <div class="flex items-center gap-1">
                                                    <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                                    <span>Masa Aktif PT:</span>
                                                </div>
                                                <span class="font-medium text-gray-800 bg-gray-50 px-2 py-1 rounded border border-gray-100">
                                                    {{ \Carbon\Carbon::parse($membership->start_date ?? $membership->created_at)->translatedFormat('d M Y') }} 
                                                    <span class="text-gray-400 mx-1">s/d</span> 
                                                    {{ \Carbon\Carbon::parse($membership->pt_end_date)->translatedFormat('d M Y') }}
                                                </span>
                                            </div>
                                        @endif

                                        @if(!is_null($membership->remaining_sessions))
                                            <div class="mt-2">
                                                <span class="inline-flex items-center gap-1.5 {{ $membership->remaining_sessions == 0 ? 'bg-red-50 text-red-700 border-red-200' : 'bg-purple-50 text-purple-700 border-purple-200' }} text-xs font-bold py-1.5 px-3 rounded-full border">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                                    Sisa {{ $membership->remaining_sessions }} dari {{ $membership->total_sessions }} Sesi
                                                </span>
                                            </div>
                                        @endif
                                    
                                    {{-- JIKA PAKET BERUPA GYM BIASA --}}
                                    @else
                                        @if($membership->membership_end_date)
                                            <div class="text-xs text-gray-500 flex flex-col sm:items-end gap-1">
                                                <div class="flex items-center gap-1">
                                                    <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                                    <span>Masa Aktif Gym:</span>
                                                </div>
                                                <span class="font-medium text-gray-800 bg-gray-50 px-2 py-1 rounded border border-gray-100">
                                                    {{ \Carbon\Carbon::parse($membership->start_date ?? $membership->created_at)->translatedFormat('d M Y') }} 
                                                    <span class="text-gray-400 mx-1">s/d</span> 
                                                    {{ \Carbon\Carbon::parse($membership->membership_end_date)->translatedFormat('d M Y') }}
                                                </span>
                                            </div>
                                        @endif
                                    @endif

                                </div>

                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="mt-6 text-center">
                <p class="text-xs text-gray-400 mb-2">Manual Input Data (Untuk Admin)</p>
                <div class="inline-block bg-gray-100 border border-gray-200 rounded-lg py-2 px-4 text-xs text-gray-600 font-mono select-all cursor-text">
                    {"user_id": {{ $user->id }}}
                </div>
            </div>
        @endif
        
    </div>
</div>