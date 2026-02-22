<?php

namespace App\Livewire\Admin; // Sesuaikan dengan namespace Anda

use Livewire\Component;
use Livewire\Attributes\Layout;
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

    public function with(): array
    {
        return [
            // Mengambil data membership beserta relasinya, diurutkan dari yang terbaru
            'memberships' => Membership::with(['user', 'personalTrainer', 'gymPackage'])
                ->latest()
                ->paginate(10),
        ];
    }
};
?>

<div>
    <div class="flex justify-between items-center mb-6">
        <h5 class="text-xl font-semibold text-heading">Membership</h5>
        
        {{-- <a href="{{ route('admin.packages.create') }}" wire:navigate class="text-white bg-brand box-border border border-transparent hover:bg-brand-strong focus:ring-4 focus:ring-brand-medium shadow-xs font-medium leading-5 rounded-md text-sm px-4 py-2.5 focus:outline-none">+ Tambah Paket</a> --}}
    </div>

    <div class="relative overflow-x-auto bg-neutral-primary-soft shadow-xs rounded-md border border-default">
        <table class="w-full text-sm text-left rtl:text-right text-body">
            <thead class="text-sm text-body bg-neutral-secondary-medium border-b border-default-medium">
                <tr>
                    <th scope="col" class="px-6 py-3 font-medium">No</th>
                    <th scope="col" class="px-6 py-3 font-medium">Member</th>
                    <th scope="col" class="px-6 py-3 font-medium">Personal Trainer</th>
                    <th scope="col" class="px-6 py-3 font-medium">Paket Gym</th>
                    <th scope="col" class="px-6 py-3 font-medium">Sesi</th>
                    <th scope="col" class="px-6 py-3 font-medium">Harga</th>
                    <th scope="col" class="px-6 py-3 font-medium">Durasi</th>
                    <th scope="col" class="px-6 py-3 font-medium text-center">Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($memberships as $membership)
                    <tr wire:key="{{ $membership->id }}" class="bg-neutral-primary-soft border-b border-default hover:bg-neutral-secondary-medium">
                        <td scope="row" class="px-7 py-4 font-medium text-heading whitespace-nowrap">
                            {{ $loop->iteration + ($memberships->currentPage() - 1) * $memberships->perPage() }}
                        </td>
                        <td scope="row" class="px-6 py-4 font-medium text-heading whitespace-nowrap">
                            <div>{{ $membership->user->name ?? 'N/A' }}</div>
                            <div class="text-xs text-gray-500">Goal: {{ $membership->member_goal ?? '-' }}</div>
                        </td>
                        <td scope="row" class="px-6 py-4 font-medium text-heading whitespace-nowrap">
                            {{ $membership->personalTrainer->name ?? '-' }}
                        </td>
                        <td scope="row" class="px-6 py-4 font-medium text-heading whitespace-nowrap">
                            {{ $membership->gymPackage->name ?? '-' }}
                        </td>
                        <td scope="row" class="px-6 py-4 font-medium text-heading whitespace-nowrap">
                            <span class="font-semibold {{ $membership->remaining_sessions <= 2 ? 'text-red-600' : 'text-green-600' }}">
                                {{ $membership->remaining_sessions }}
                            </span> 
                            / {{ $membership->total_sessions }}
                        </td>
                        <td scope="row" class="px-6 py-4 font-medium text-heading whitespace-nowrap">
                            Rp {{ number_format($membership->price_paid, 0, ',', '.') }}
                        </td>
                        <td scope="row" class="px-6 py-4 font-medium text-heading whitespace-nowrap">
                            <div>{{ $membership->start_date ? $membership->start_date->format('d M Y') : '-' }}</div>
                            <div>s/d</div>
                            <div>{{ $membership->end_date ? $membership->end_date->format('d M Y') : '-' }}</div>
                        </td>

                        <td scope="row" class="px-6 py-4 font-medium text-heading whitespace-nowrap text-center">
    
                            {{-- TAMPILAN LABEL STATUS --}}
                            @if ($membership->status === 'pending')
                                <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800 mb-2">
                                    Pending Payment
                                </span>
                                
                                {{-- TOMBOL AKSI HANYA MUNCUL JIKA STATUS PENDING --}}
                                <div class="flex justify-center space-x-2 mt-1">
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
                                <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                    Active
                                </span>
                                
                            @elseif ($membership->status === 'expired')
                                <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">
                                    Expired
                                </span>

                            @elseif ($membership->status === 'completed')
                                <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                    Completed (Sesi Habis)
                                </span>
                                
                            @elseif ($membership->status === 'rejected')
                                <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                    Rejected
                                </span>
                            @endif
                            
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-6 py-4 text-center text-gray-500">
                            Belum ada data paket membership.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    
    <div class="mt-4">
        {{ $memberships->links() }}
    </div>
</div>