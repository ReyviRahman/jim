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
        // Mengambil data membership milik user yang login beserta relasinya
        return Membership::with(['gymPackage', 'personalTrainer'])
            ->where('user_id', Auth::id())
            ->latest() // Sama dengan orderBy('created_at', 'desc')
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
                    <th scope="col" class="px-6 py-3 font-medium">Paket & Goal</th>
                    <th scope="col" class="px-6 py-3 font-medium">Personal Trainer</th>
                    <th scope="col" class="px-6 py-3 font-medium text-center">Sesi Bersama Coach</th>
                    <th scope="col" class="px-6 py-3 font-medium text-right">Total Bayar</th> {{-- Kolom Baru --}}
                    <th scope="col" class="px-6 py-3 font-medium text-center">Masa Aktif</th>
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
                        
                        {{-- Paket & Goal --}}
                        <td class="px-6 py-4 font-medium text-heading whitespace-nowrap">
                            <div class="font-semibold text-brand-strong">{{ $membership->gymPackage->name ?? 'Paket Dihapus' }}</div>
                            <div class="text-xs text-gray-500 mt-1">Goal: {{ $membership->member_goal ?? '-' }}</div>
                        </td>
                        
                        {{-- Personal Trainer --}}
                        <td class="px-6 py-4 font-medium text-heading whitespace-nowrap">
                            {{ $membership->personalTrainer->name ?? 'Mandiri (Tanpa PT)' }}
                        </td>
                        
                        {{-- Sesi --}}
                        <td class="px-6 py-4 text-center whitespace-nowrap">
                            @if($membership->total_sessions)
                                <span class="font-bold {{ $membership->remaining_sessions <= 2 ? 'text-red-600' : 'text-green-600' }}">
                                    {{ $membership->remaining_sessions }}
                                </span> 
                                / {{ $membership->total_sessions }} Sesi
                            @else
                                <span class="px-2.5 py-1 bg-gray-100 text-gray-600 text-xs font-medium rounded-md">-</span>
                            @endif
                        </td>

                        {{-- Total Bayar & Rincian Diskon --}}
                        {{-- Total Bayar & Rincian Diskon --}}
                        <td class="px-6 py-4 text-right whitespace-nowrap">
                            {{-- Gunakan discount_applied --}}
                            @if($membership->discount_applied > 0)
                                @php
                                    // Hitung harga asli (Total Bayar + Diskon Nominal)
                                    $originalPrice = $membership->price_paid + $membership->discount_applied;
                                    
                                    // Hitung persentase diskon untuk badge
                                    $percentage = ($originalPrice > 0) ? ($membership->discount_applied / $originalPrice) * 100 : 0;
                                @endphp
                                
                                <div class="flex flex-col items-end mb-1">
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs text-gray-400 line-through">Rp {{ number_format($originalPrice, 0, ',', '.') }}</span>
                                        <span class="bg-green-100 text-green-800 text-[10px] font-bold px-1.5 py-0.5 rounded">
                                            -{{ is_float($percentage) ? round($percentage, 1) : $percentage }}%
                                        </span>
                                    </div>
                                    {{-- Info tambahan biar member makin seneng dapet diskon --}}
                                    <div class="text-[10px] text-green-600 font-medium mt-0.5">
                                        Diskon Rp {{ number_format($membership->discount_applied, 0, ',', '.') }}
                                    </div>
                                </div>
                            @endif
                            
                            <div class="font-bold text-heading text-base">
                                Rp {{ number_format($membership->price_paid, 0, ',', '.') }}
                            </div>
                        </td>
                        
                        {{-- Masa Aktif --}}
                        <td class="px-6 py-4 text-center whitespace-nowrap text-xs">
                            <div class="text-heading font-medium">{{ $membership->start_date ? $membership->start_date->format('d M Y') : '-' }}</div>
                            <div class="text-gray-400 my-0.5">s/d</div>
                            <div class="text-heading font-medium">{{ $membership->end_date ? $membership->end_date->format('d M Y') : '-' }}</div>
                        </td>
                        
                        {{-- Status --}}
                        <td class="px-6 py-4 text-center whitespace-nowrap">
                            @if($membership->status === 'active')
                                <span class="bg-green-100 text-green-800 text-xs font-semibold px-2.5 py-1 rounded-full border border-green-200">Aktif</span>
                            @elseif($membership->status === 'pending')
                                <span class="bg-yellow-100 text-yellow-800 text-xs font-semibold px-2.5 py-1 rounded-full border border-yellow-200">Menunggu Pembayaran</span>
                            @elseif($membership->status === 'expired')
                                <span class="bg-gray-100 text-gray-800 text-xs font-semibold px-2.5 py-1 rounded-full border border-gray-200">Expired</span>
                            @elseif($membership->status === 'completed')
                                <span class="bg-blue-100 text-blue-800 text-xs font-semibold px-2.5 py-1 rounded-full border border-blue-200">Selesai</span>
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