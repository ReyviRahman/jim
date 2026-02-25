<?php

namespace App\Livewire\Member; // Sesuaikan dengan namespace kamu

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use App\Models\Membership;
use Illuminate\Support\Facades\Auth; 
use Livewire\WithPagination;

new #[Layout('layouts::member')] class extends Component
{
    use WithPagination;

    #[Computed]
    public function memberships()
    {
        // TAMBAHKAN 'ptPackage' ke dalam array with()
        return Membership::with(['gymPackage', 'ptPackage', 'personalTrainer'])
            ->whereHas('members', function($q) {
                // Gunakan relasi members (pivot) agar user yang menjadi 'anggota' juga bisa melihat riwayatnya,
                // tidak hanya user yang menjadi 'pembayar utama' (user_id)
                $q->where('user_id', Auth::id());
            })
            ->orWhere('user_id', Auth::id()) // Tampilkan juga jika dia adalah pembayar utama
            ->latest() 
            ->paginate(10);
    }
};
?>

<div>
    <div class="flex justify-between items-center mb-6">
        <h5 class="text-xl font-semibold text-heading">Riwayat Membership Saya</h5>
    </div>

    <div class="relative overflow-x-auto bg-neutral-primary-soft shadow-xs rounded-md border border-default">
        <table class="w-full text-sm text-left rtl:text-right text-body">
            <thead class="text-sm text-body bg-neutral-secondary-medium border-b border-default-medium">
                <tr>
                    <th scope="col" class="px-6 py-3 font-medium">No</th>
                    <th scope="col" class="px-6 py-3 font-medium">Tipe Layanan</th>
                    <th scope="col" class="px-6 py-3 font-medium">Detail Program</th>
                    <th scope="col" class="px-6 py-3 font-medium text-center">Sesi Coach</th>
                    <th scope="col" class="px-6 py-3 font-medium text-right">Total Bayar</th>
                    <th scope="col" class="px-6 py-3 font-medium">Masa Aktif</th>
                    <th scope="col" class="px-6 py-3 font-medium text-center">Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->memberships as $membership)
                    <tr wire:key="{{ $membership->id }}" class="bg-neutral-primary-soft border-b border-default hover:bg-neutral-secondary-medium">
                        
                        {{-- Nomor --}}
                        <td class="px-6 py-4 font-medium text-heading whitespace-nowrap">
                            {{ $loop->iteration + ($this->memberships->currentPage() - 1) * $this->memberships->perPage() }}
                        </td>

                        {{-- TIPE LAYANAN --}}
                        <td class="px-6 py-4 whitespace-nowrap">
                            @if ($membership->type === 'membership')
                                <span class="bg-emerald-50 text-emerald-700 text-xs font-semibold px-2.5 py-1 rounded-md border border-emerald-200">Gym Bulanan</span>
                            @elseif ($membership->type === 'pt')
                                <span class="bg-indigo-50 text-indigo-700 text-xs font-semibold px-2.5 py-1 rounded-md border border-indigo-200">PT Only</span>
                            @elseif ($membership->type === 'bundle_pt_membership')
                                <span class="bg-purple-50 text-purple-700 text-xs font-semibold px-2.5 py-1 rounded-md border border-purple-200">Bundle (Gym + PT)</span>
                            @elseif ($membership->type === 'visit')
                                <span class="bg-orange-50 text-orange-700 text-xs font-semibold px-2.5 py-1 rounded-md border border-orange-200">Visit / Harian</span>
                            @else
                                <span class="bg-gray-100 text-gray-800 text-xs font-medium px-2.5 py-1 rounded-md">-</span>
                            @endif
                        </td>
                        
                        {{-- DETAIL PROGRAM & GOAL --}}
                        <td class="px-6 py-4 text-heading whitespace-nowrap">
                            <div class="flex flex-col gap-2">
                                
                                {{-- Jika ada Paket Gym / Visit --}}
                                @if(in_array($membership->type, ['membership', 'bundle_pt_membership', 'visit']))
                                    <div>
                                        <div class="text-[10px] text-gray-400 uppercase tracking-wider font-bold mb-0.5">
                                            Paket {{ $membership->type === 'visit' ? 'Harian' : 'Gym' }}
                                        </div>
                                        <div class="font-medium {{ $membership->type === 'visit' ? 'text-orange-600' : 'text-emerald-600' }}">
                                            {{ $membership->gymPackage->name ?? 'Paket Terhapus' }}
                                        </div>
                                    </div>
                                @endif

                                {{-- Jika ada Paket PT --}}
                                @if(in_array($membership->type, ['pt', 'bundle_pt_membership']))
                                    <div class="{{ in_array($membership->type, ['bundle_pt_membership']) ? 'border-t border-gray-200 pt-2' : '' }}">
                                        <div class="text-[10px] text-gray-400 uppercase tracking-wider font-bold mb-0.5">Paket Trainer</div>
                                        <div class="font-medium text-indigo-600">{{ $membership->ptPackage->name ?? 'Paket Terhapus' }}</div>
                                        <div class="text-xs text-gray-500 mt-0.5">
                                            Coach: <span class="font-medium text-gray-700">{{ $membership->personalTrainer->name ?? '-' }}</span>
                                        </div>
                                    </div>
                                @endif

                                @if($membership->member_goal)
                                    <div class="text-[11px] text-gray-500 mt-1.5 pt-1.5 border-t border-gray-100">
                                        🎯 Goal: {{ $membership->member_goal }}
                                    </div>
                                @endif
                            </div>
                        </td>
                        
                        {{-- Sesi Coach --}}
                        <td class="px-6 py-4 text-center whitespace-nowrap">
                            @if($membership->total_sessions)
                                <span class="font-bold {{ $membership->remaining_sessions <= 2 ? 'text-red-600' : 'text-green-600' }}">
                                    {{ $membership->remaining_sessions }}
                                </span> 
                                <span class="text-gray-400">/ {{ $membership->total_sessions }}</span>
                            @else
                                <span class="px-2.5 py-1 bg-gray-100 text-gray-400 text-xs font-medium rounded-md">-</span>
                            @endif
                        </td>

                        {{-- Total Bayar --}}
                        <td class="px-6 py-4 text-right whitespace-nowrap">
                            @if($membership->discount_applied > 0)
                                @php
                                    $originalPrice = $membership->price_paid + $membership->discount_applied;
                                    $percentage = ($originalPrice > 0) ? ($membership->discount_applied / $originalPrice) * 100 : 0;
                                @endphp
                                
                                <div class="flex flex-col items-end mb-1">
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs text-gray-400 line-through">Rp {{ number_format($originalPrice, 0, ',', '.') }}</span>
                                        <span class="bg-green-100 text-green-800 text-[10px] font-bold px-1.5 py-0.5 rounded">
                                            -{{ is_float($percentage) ? round($percentage, 1) : $percentage }}%
                                        </span>
                                    </div>
                                </div>
                            @endif
                            
                            <div class="font-bold text-heading text-base">
                                Rp {{ number_format($membership->price_paid, 0, ',', '.') }}
                            </div>
                        </td>
                        
                        {{-- Masa Aktif --}}
                        <td class="px-6 py-4 whitespace-nowrap text-xs">
                            <div class="flex flex-col gap-1.5">
                                <div class="flex items-center text-gray-600">
                                    <svg class="w-3.5 h-3.5 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                    Mulai: <span class="font-medium text-heading ml-1">{{ $membership->start_date ? $membership->start_date->format('d M Y') : '-' }}</span>
                                </div>

                                @if(in_array($membership->type, ['membership', 'bundle_pt_membership']))
                                    <div class="flex items-center text-gray-600">
                                        <span class="inline-block w-2 h-2 rounded-full bg-emerald-400 mr-2"></span>
                                        Gym s/d: <span class="font-medium text-emerald-600 ml-1">{{ $membership->membership_end_date ? $membership->membership_end_date->format('d M Y') : '-' }}</span>
                                    </div>
                                @endif

                                @if($membership->type === 'visit')
                                    <div class="flex items-center text-gray-600 mt-0.5">
                                        <span class="inline-block w-2 h-2 rounded-full bg-orange-400 mr-2"></span>
                                        <span class="font-medium text-orange-600 ml-1">Berlaku 1 Hari</span>
                                    </div>
                                @endif

                                @if(in_array($membership->type, ['pt', 'bundle_pt_membership']))
                                    <div class="flex items-center text-gray-600">
                                        <span class="inline-block w-2 h-2 rounded-full bg-indigo-400 mr-2"></span>
                                        PT s/d: <span class="font-medium text-indigo-600 ml-1">{{ $membership->pt_end_date ? $membership->pt_end_date->format('d M Y') : '-' }}</span>
                                    </div>
                                @endif
                            </div>
                        </td>
                        
                        {{-- Status --}}
                        <td class="px-6 py-4 text-center whitespace-nowrap">
                            @if($membership->status === 'active')
                                <span class="bg-green-100 text-green-800 text-xs font-semibold px-2.5 py-1 rounded-full border border-green-200">Aktif</span>
                            @elseif($membership->status === 'pending')
                                <span class="bg-yellow-100 text-yellow-800 text-xs font-semibold px-2.5 py-1 rounded-full border border-yellow-200">Menunggu Pembayaran</span>
                            @elseif($membership->status === 'expired')
                                <span class="bg-gray-100 text-gray-800 text-xs font-semibold px-2.5 py-1 rounded-full border border-gray-200">Kedaluwarsa</span>
                            @elseif($membership->status === 'completed')
                                <span class="bg-blue-100 text-blue-800 text-xs font-semibold px-2.5 py-1 rounded-full border border-blue-200">Selesai / Sesi Habis</span>
                            @elseif($membership->status === 'rejected')
                                <span class="bg-red-100 text-red-800 text-xs font-semibold px-2.5 py-1 rounded-full border border-red-200">Dibatalkan</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-6 py-8 text-center text-gray-500">
                            Anda belum memiliki riwayat paket membership saat ini.
                        </td>
                    </tr>
                @endforelse
                
            </tbody>
        </table>
    </div>

    {{-- Link Pagination --}}
    <div class="mt-4">
        {{ $this->memberships->links() }}
    </div>
</div>