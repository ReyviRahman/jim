<?php

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
            // Ambil data absensi khusus untuk user yang sedang login
            'attendances' => Attendance::with('membership.gymPackage')
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
                        <th scope="col" class="px-6 py-4 font-semibold">Tipe Kedatangan</th>
                        <th scope="col" class="px-6 py-4 font-semibold">Paket Gym / Info</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($attendances as $absen)
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="font-medium text-gray-800">{{ $absen->check_in_time->format('d M Y') }}</div>
                                <div class="text-xs text-gray-500">{{ $absen->check_in_time->format('H:i') }} WIB</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($absen->type === 'membership')
                                        <span class="px-2.5 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-700 border border-green-200">
                                            Membership
                                        </span>
                                @else
                                    <span class="px-2.5 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-700 border border-yellow-200">
                                        Visit Harian
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($absen->type === 'membership' && $absen->membership)
                                    <span class="font-medium text-gray-700">{{ $absen->membership->gymPackage->name ?? 'Paket Gym' }}</span>
                                @else
                                    <span class="text-gray-400">-</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-6 py-10 text-center">
                                <svg class="w-12 h-12 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                </svg>
                                <p class="text-gray-500 font-medium">Belum ada riwayat kehadiran.</p>
                                <p class="text-xs text-gray-400 mt-1">Ayo mulai latihan pertamamu di Frans Gym!</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($attendances->hasPages())
            <div class="px-6 py-4 border-t border-gray-100">
                {{ $attendances->links() }} 
            </div>
        @endif
    </div>
</div>