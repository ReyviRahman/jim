<?php

namespace App\Livewire\Member;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use App\Models\Attendance;
use Illuminate\Support\Facades\Auth;

new #[Layout('layouts::member')] class extends Component
{
    use WithPagination;

    public function with(): array
    {
        return [
            // Tambahkan ptPackage agar data Personal Trainer juga ikut terbaca
            'attendances' => Attendance::with(['membership.gymPackage', 'membership.ptPackage'])
                ->where('user_id', Auth::id())
                ->latest('check_in_time')
                ->paginate(10),
        ];
    }
};
?>

<div class="max-w-4xl mx-auto p-4 sm:p-6 lg:p-8">
    <div class="mb-6">
        <h2 class="text-2xl font-bold text-gray-800">Riwayat Kehadiran</h2>
        <p class="text-sm text-gray-500 mt-1">Daftar riwayat kedatangan dan penggunaan sesi kamu di Frans Gym.</p>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left text-gray-600">
                <thead class="text-xs text-gray-700 uppercase bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th scope="col" class="px-6 py-4 font-semibold">Tanggal & Waktu</th>
                        <th scope="col" class="px-6 py-4 font-semibold">Status Kedatangan</th>
                        <th scope="col" class="px-6 py-4 font-semibold">Detail Paket</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($attendances as $absen)
                        <tr class="hover:bg-gray-50 transition-colors">
                            
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="font-medium text-gray-800">
                                    {{ \Carbon\Carbon::parse($absen->check_in_time)->translatedFormat('d M Y') }}
                                </div>
                                <div class="text-xs text-gray-500 flex items-center gap-1 mt-0.5">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    {{ \Carbon\Carbon::parse($absen->check_in_time)->format('H:i') }} WIB
                                </div>
                            </td>

                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($absen->type === 'gym')
                                    @if($absen->membership && $absen->membership->type === 'pt')
                                        <span class="px-2.5 py-1 inline-flex items-center gap-1 text-xs leading-5 font-semibold rounded-full bg-purple-100 text-purple-700 border border-purple-200">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                            Sesi PT
                                        </span>
                                    @else
                                        <span class="px-2.5 py-1 inline-flex items-center gap-1 text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-700 border border-green-200">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                            Member Gym
                                        </span>
                                    @endif
                                @else
                                    <span class="px-2.5 py-1 inline-flex items-center gap-1 text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-700 border border-yellow-200">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"></path></svg>
                                        Visit Harian
                                    </span>
                                @endif
                            </td>

                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($absen->type === 'gym' && $absen->membership)
                                    
                                    @if($absen->membership->type === 'pt' && $absen->membership->ptPackage)
                                        <div class="font-bold text-gray-800">{{ $absen->membership->ptPackage->name }}</div>
                                        <div class="text-xs text-purple-600 font-medium">Personal Trainer</div>
                                    
                                    @elseif($absen->membership->gymPackage)
                                        <div class="font-bold text-gray-800">{{ $absen->membership->gymPackage->name }}</div>
                                        <div class="text-xs text-blue-600 font-medium">Akses Gym</div>
                                        
                                    @else
                                        <span class="font-medium text-gray-700">Paket Kustom</span>
                                    @endif

                                @else
                                    <span class="text-gray-400 italic">Tanpa Paket / Harian</span>
                                @endif
                            </td>

                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-6 py-12 text-center">
                                <svg class="w-12 h-12 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                </svg>
                                <p class="text-gray-500 font-medium text-base">Belum ada riwayat kehadiran.</p>
                                <p class="text-sm text-gray-400 mt-1">Ayo mulai latihan pertamamu di Frans Gym!</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($attendances->hasPages())
            <div class="px-6 py-4 border-t border-gray-100 bg-gray-50">
                {{ $attendances->links() }} 
            </div>
        @endif
    </div>
</div>