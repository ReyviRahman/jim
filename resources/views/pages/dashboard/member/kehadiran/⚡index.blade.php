<?php

namespace App\Livewire\Member; // Sesuaikan jika namespace kamu berbeda

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
            // Eager loading sudah benar, memanggil user tidak perlu karena ini halaman milik user itu sendiri
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
        <h2 class="text-2xl font-bold text-heading">Riwayat Kehadiran</h2>
        <p class="text-sm text-body mt-1">Daftar riwayat kedatangan dan penggunaan sesi kamu di Frans Gym.</p>
    </div>

    <div class="relative overflow-x-auto bg-neutral-primary-soft shadow-xs rounded-md border border-default">
        <table class="w-full text-sm text-left rtl:text-right text-body">
            
            <thead class="text-sm text-body bg-neutral-secondary-medium border-b border-default-medium">
                <tr>
                    <th scope="col" class="px-6 py-3 font-medium">Tanggal & Waktu</th>
                    <th scope="col" class="px-6 py-3 font-medium">Tipe Kedatangan</th>
                    <th scope="col" class="px-6 py-3 font-medium">Detail Paket</th>
                </tr>
            </thead>
            
            <tbody>
                @forelse ($attendances as $absen)
                    <tr wire:key="{{ $absen->id }}" class="bg-neutral-primary-soft border-b border-default hover:bg-neutral-secondary-medium transition-colors">
                        
                        <td class="px-6 py-4 font-medium text-heading whitespace-nowrap">
                            <div class="flex items-center text-gray-600">
                                {{ \Carbon\Carbon::parse($absen->check_in_time)->format('d M Y') }}
                                <span class="ml-2 font-bold text-gray-800">
                                    {{ \Carbon\Carbon::parse($absen->check_in_time)->format('H:i') }}
                                </span>
                            </div>
                        </td>

                        <td class="px-6 py-4 whitespace-nowrap">
                            @if($absen->type === 'gym')
                                <span class="px-2.5 py-1 inline-flex text-xs leading-5 font-semibold rounded-md bg-emerald-100 text-emerald-800 border border-emerald-200">
                                    🏋️ Gym Mandiri
                                </span>
                            @elseif($absen->type === 'pt')
                                <span class="px-2.5 py-1 inline-flex text-xs leading-5 font-semibold rounded-md bg-indigo-100 text-indigo-800 border border-indigo-200">
                                    👨‍🏫 Sesi PT
                                </span>
                            @elseif($absen->type === 'visit')
                                <span class="px-2.5 py-1 inline-flex text-xs leading-5 font-semibold rounded-md bg-orange-100 text-orange-800 border border-orange-200">
                                    🎟️ Visit Harian
                                </span>
                            @endif
                        </td>

                        <td class="px-6 py-4 font-medium text-heading whitespace-nowrap">
                            @if($absen->membership)
                                <div class="flex flex-col gap-1">
                                    
                                    @if(in_array($absen->type, ['gym', 'visit']) && $absen->membership->gymPackage)
                                        <div class="text-sm font-semibold text-emerald-700">
                                            {{ $absen->membership->gymPackage->name }}
                                        </div>
                                    @endif

                                    @if($absen->type === 'pt' && $absen->membership->ptPackage)
                                        <div class="text-sm font-semibold text-indigo-700">
                                            {{ $absen->membership->ptPackage->name }}
                                        </div>
                                        <div class="text-xs text-gray-600 mt-0.5">
                                            Coach: <span class="font-bold">{{ $absen->membership->personalTrainer->name ?? '-' }}</span>
                                        </div>
                                    @endif

                                </div>
                            @else
                                <span class="text-red-500 italic">-</span>
                            @endif
                        </td>

                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="px-6 py-12 text-center text-gray-500">
                            <svg class="w-12 h-12 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                            <p class="font-medium text-base">Belum ada riwayat kehadiran.</p>
                            <p class="text-sm text-gray-400 mt-1">Ayo mulai latihan pertamamu di Frans Gym!</p>
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