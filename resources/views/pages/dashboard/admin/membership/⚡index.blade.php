<?php

namespace App\Livewire\Admin; // Sesuaikan dengan namespace Anda

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed; // Tambahkan ini
use App\Models\Membership;
use Livewire\WithPagination;

new #[Layout('layouts::admin')] class extends Component
{
    use WithPagination;

    // Method KHUSUS untuk menyetujui member yang baru daftar (Pending -> Active)
    public function approve($membershipId)
    {
        $membership = Membership::findOrFail($membershipId);
        
        // Proteksi Lapis Ganda: Hanya status "pending" yang boleh di-"Approve"
        if ($membership->status === 'pending') {
            $membership->update([
                'status' => 'active'
            ]);
            
            session()->flash('success', "Membership {$membership->user->name} berhasil diaktifkan!");
        } else {
            session()->flash('error', "Gagal: Membership ini tidak dalam status pending.");
        }
    }

    // Method KHUSUS untuk menolak member (Pending -> Rejected)
    public function reject($membershipId)
    {
        $membership = Membership::findOrFail($membershipId);
        
        // Proteksi Lapis Ganda: Hanya status "pending" yang boleh di-"Reject"
        if ($membership->status === 'pending') {
            $membership->update([
                'status' => 'rejected'
            ]);

            session()->flash('success', "Pengajuan membership {$membership->user->name} berhasil ditolak.");
        } else {
            session()->flash('error', "Gagal: Membership ini tidak dalam status pending.");
        }
    }

    // Menggunakan Computed Property agar lebih modern dan efisien
    #[Computed]
    public function memberships()
    {
        return Membership::with(['user', 'personalTrainer', 'gymPackage'])
            ->latest()
            ->paginate(10);
    }
};
?>

<div>
    <div class="flex sm:flex-row flex-col justify-between items-center mb-6">
        <h5 class="text-xl font-semibold text-heading">Membership</h5>
        <a href="{{ route('admin.membership.gabung') }}" wire:navigate class="text-white bg-brand box-border border border-transparent hover:bg-brand-strong focus:ring-4 focus:ring-brand-medium shadow-xs font-medium leading-5 rounded-md text-sm px-4 py-2.5 focus:outline-none">+ Gabung Membership</a>
    </div>

    {{-- Notifikasi --}}
    @if (session()->has('success'))
        <div class="p-4 mb-4 text-sm text-green-800 rounded-lg bg-green-50" role="alert">
            <span class="font-medium">Sukses!</span> {{ session('success') }}
        </div>
    @endif
    @if (session()->has('error'))
        <div class="p-4 mb-4 text-sm text-red-800 rounded-lg bg-red-50" role="alert">
            <span class="font-medium">Gagal!</span> {{ session('error') }}
        </div>
    @endif

    <div class="relative overflow-x-auto bg-neutral-primary-soft shadow-xs rounded-md border border-default">
        <table class="w-full text-sm text-left rtl:text-right text-body">
            <thead class="text-sm text-body bg-neutral-secondary-medium border-b border-default-medium">
                <tr>
                    <th scope="col" class="px-6 py-3 font-medium">No</th>
                    <th scope="col" class="px-6 py-3 font-medium">Member</th>
                    <th scope="col" class="px-6 py-3 font-medium">Paket & Trainer</th>
                    <th scope="col" class="px-6 py-3 font-medium text-center">Sesi</th>
                    <th scope="col" class="px-6 py-3 font-medium text-right">Total Bayar</th>
                    <th scope="col" class="px-6 py-3 font-medium text-center">Masa Aktif</th>
                    <th scope="col" class="px-6 py-3 font-medium text-center">Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->memberships as $membership)
                    <tr wire:key="{{ $membership->id }}" class="bg-neutral-primary-soft border-b border-default hover:bg-neutral-secondary-medium">
                        
                        {{-- Nomor Urut --}}
                        <td class="px-6 py-4 font-medium text-heading whitespace-nowrap">
                            {{ $loop->iteration + ($this->memberships->currentPage() - 1) * $this->memberships->perPage() }}
                        </td>
                        
                        {{-- Info Member --}}
                        <td class="px-6 py-4 font-medium text-heading whitespace-nowrap">
                            <div class="font-semibold">{{ $membership->user->name ?? 'N/A' }}</div>
                            <div class="text-xs text-gray-500 mt-1">Goal: {{ $membership->member_goal ?? '-' }}</div>
                        </td>

                        {{-- Info Paket & PT --}}
                        <td class="px-6 py-4 text-heading whitespace-nowrap">
                            <div class="font-medium text-brand-strong">{{ $membership->gymPackage->name ?? '-' }}</div>
                            <div class="text-xs text-gray-500 mt-1">
                                PT: <span class="font-medium">{{ $membership->personalTrainer->name ?? 'Mandiri (Tanpa PT)' }}</span>
                            </div>
                        </td>

                        {{-- Info Sesi (Menyesuaikan Jika Mandiri/Unlimited) --}}
                        <td class="px-6 py-4 text-center whitespace-nowrap">
                            @if ($membership->total_sessions)
                                <span class="font-bold {{ $membership->remaining_sessions <= 2 ? 'text-red-600' : 'text-green-600' }}">
                                    {{ $membership->remaining_sessions }}
                                </span> 
                                / {{ $membership->total_sessions }}
                            @else
                                <span class="px-2.5 py-1 bg-gray-100 text-gray-600 text-xs font-medium rounded-md">Unlimited</span>
                            @endif
                        </td>

                        {{-- Total Bayar --}}
                        {{-- Total Bayar & Rincian --}}
                        {{-- Total Bayar & Rincian --}}
                        <td class="px-6 py-4 text-right whitespace-nowrap">
                            {{-- Gunakan discount_applied karena discount_percentage sudah dihapus --}}
                            @if($membership->discount_applied > 0)
                                @php
                                    // Hitung harga asli (Total Bayar + Diskon Nominal)
                                    $originalPrice = $membership->price_paid + $membership->discount_applied;
                                    
                                    // Hitung persentase diskon secara dinamis untuk badge
                                    $percentage = ($originalPrice > 0) ? ($membership->discount_applied / $originalPrice) * 100 : 0;
                                @endphp
                                
                                <div class="flex flex-col items-end mb-1">
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs text-gray-400 line-through">Rp {{ number_format($originalPrice, 0, ',', '.') }}</span>
                                        {{-- Badge Persentase --}}
                                        <span class="bg-green-100 text-green-800 text-[10px] font-bold px-1.5 py-0.5 rounded">
                                            -{{ is_float($percentage) ? round($percentage, 1) : $percentage }}%
                                        </span>
                                    </div>
                                    {{-- Opsional: Tampilkan nominal hematnya --}}
                                    <div class="text-[10px] text-green-600 font-medium mt-0.5">
                                        Diskon Rp {{ number_format($membership->discount_applied, 0, ',', '.') }}
                                    </div>
                                </div>
                            @endif
                            
                            {{-- Harga Final (Total Bayar) --}}
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

                        {{-- Status & Aksi --}}
                        <td class="px-6 py-4 text-center whitespace-nowrap">
                            @if ($membership->status === 'pending')
                                <span class="px-2 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800 mb-2 block w-max mx-auto">
                                    Pending Payment
                                </span>
                                <div class="flex justify-center space-x-2 mt-2">
                                    <button 
                                        wire:click="approve({{ $membership->id }})"
                                        wire:confirm="Yakin ingin MENGAKTIFKAN membership ini? (Pastikan member sudah membayar)"
                                        class="bg-green-500 hover:bg-green-600 text-white p-1.5 rounded shadow-sm transition-colors"
                                        title="Aktifkan (Sudah Bayar)"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                    </button>
                                    <button 
                                        wire:click="reject({{ $membership->id }})"
                                        wire:confirm="Yakin ingin MENOLAK/MEMBATALKAN pengajuan membership ini?"
                                        class="bg-red-500 hover:bg-red-600 text-white p-1.5 rounded shadow-sm transition-colors"
                                        title="Tolak / Batal"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                    </button>
                                </div>
                            @elseif ($membership->status === 'active')
                                <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">Active</span>
                            @elseif ($membership->status === 'expired')
                                <span class="px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">Expired</span>
                            @elseif ($membership->status === 'completed')
                                <span class="px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">Sesi Habis</span>
                            @elseif ($membership->status === 'rejected')
                                <span class="px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">Rejected</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-6 py-8 text-center text-gray-500">
                            Belum ada riwayat transaksi membership.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    
    <div class="mt-4">
        {{ $this->memberships->links() }}
    </div>
</div>