<?php

namespace App\Livewire\Admin; 

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed; 
use App\Models\Membership;
use Livewire\WithPagination;

new #[Layout('layouts::admin')] class extends Component
{
    use WithPagination;

    public function approve($membershipId)
    {
        $membership = Membership::findOrFail($membershipId);
        
        if ($membership->status === 'pending') {
            $membership->update([
                'status' => 'active'
            ]);
            
            session()->flash('success', "Membership berhasil diaktifkan!");
        } else {
            session()->flash('error', "Gagal: Membership ini tidak dalam status pending.");
        }
    }

    public function reject($membershipId)
    {
        $membership = Membership::findOrFail($membershipId);
        
        if ($membership->status === 'pending') {
            $membership->update([
                'status' => 'rejected'
            ]);

            session()->flash('success', "Pengajuan membership berhasil ditolak.");
        } else {
            session()->flash('error', "Gagal: Membership ini tidak dalam status pending.");
        }
    }

    #[Computed]
    public function memberships()
    {
        // Tambahkan ptPackage ke dalam eager loading
        return Membership::with(['user', 'members', 'personalTrainer', 'gymPackage', 'ptPackage'])
            ->latest()
            ->paginate(10);
    }
};
?>

<div>
    <div class="flex sm:flex-row flex-col justify-between items-center mb-6">
        <h5 class="text-xl font-semibold text-heading">Data Membership & Program</h5>
        <a href="{{ route('admin.membership.gabung') }}" wire:navigate class="text-white bg-brand box-border border border-transparent hover:bg-brand-strong focus:ring-4 focus:ring-brand-medium shadow-xs font-medium leading-5 rounded-md text-sm px-4 py-2.5 focus:outline-none">+ Pendaftaran Baru</a>
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
                    <th scope="col" class="px-6 py-3 font-medium">Tipe</th>
                    <th scope="col" class="px-6 py-3 font-medium">Member</th>
                    <th scope="col" class="px-6 py-3 font-medium">Program / Paket</th>
                    <th scope="col" class="px-6 py-3 font-medium text-center">Sesi Coach</th>
                    <th scope="col" class="px-6 py-3 font-medium text-right">Total Bayar</th>
                    <th scope="col" class="px-6 py-3 font-medium">Masa Aktif</th>
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

                        {{-- TIPE PENDAFTARAN (DIPERBARUI) --}}
                        <td class="px-6 py-4 whitespace-nowrap">
                            @if ($membership->type === 'membership')
                                <span class="bg-emerald-50 text-emerald-700 text-xs font-semibold px-2.5 py-1 rounded-md border border-emerald-200">Membership Only</span>
                            @elseif ($membership->type === 'pt')
                                <span class="bg-indigo-50 text-indigo-700 text-xs font-semibold px-2.5 py-1 rounded-md border border-indigo-200">PT Only</span>
                            @elseif ($membership->type === 'bundle_pt_membership')
                                <span class="bg-purple-50 text-purple-700 text-xs font-semibold px-2.5 py-1 rounded-md border border-purple-200">Bundle (Gym + PT)</span>
                            @elseif ($membership->type === 'visit')
                                {{-- BADGE BARU UNTUK VISIT --}}
                                <span class="bg-orange-50 text-orange-700 text-xs font-semibold px-2.5 py-1 rounded-md border border-orange-200">Visit / Harian</span>
                            @else
                                <span class="bg-gray-100 text-gray-800 text-xs font-medium px-2.5 py-1 rounded-md">-</span>
                            @endif
                        </td>
                        
                        {{-- INFO MEMBER --}}
                        <td class="px-6 py-4 font-medium text-heading whitespace-nowrap">
                            <div class="flex flex-col gap-1.5">
                                @forelse($membership->members as $member)
                                    <div class="flex items-center gap-2">
                                        <span class="font-semibold">{{ $member->name }}</span>
                                    </div>
                                @empty
                                    <div class="font-semibold">{{ $membership->user->name ?? 'N/A' }}</div>
                                @endforelse
                            </div>
                            <div class="text-xs text-gray-500 mt-2">Goal: {{ $membership->member_goal ?? '-' }}</div>
                        </td>

                        {{-- INFO PROGRAM / PAKET (DIPERBARUI) --}}
                        <td class="px-6 py-4 text-heading whitespace-nowrap">
                            <div class="flex flex-col gap-2">
                                
                                {{-- Jika ada Paket Gym (Termasuk Visit) --}}
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
                            </div>
                        </td>

                        {{-- Info Sesi --}}
                        <td class="px-6 py-4 text-center whitespace-nowrap">
                            @if ($membership->total_sessions)
                                <span class="font-bold {{ $membership->remaining_sessions <= 2 ? 'text-red-600' : 'text-green-600' }}">
                                    {{ $membership->remaining_sessions }}
                                </span> 
                                <span class="text-gray-400">/ {{ $membership->total_sessions }}</span>
                            @else
                                <span class="px-2.5 py-1 bg-gray-100 text-gray-500 text-xs font-medium rounded-md">-</span>
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
                                    <div class="text-[10px] text-green-600 font-medium mt-0.5">
                                        Diskon Rp {{ number_format($membership->discount_applied, 0, ',', '.') }}
                                    </div>
                                </div>
                            @endif
                            
                            <div class="font-bold text-heading text-base">
                                Rp {{ number_format($membership->price_paid, 0, ',', '.') }}
                            </div>
                        </td>

                        {{-- Masa Aktif (DIPERBARUI) --}}
                        <td class="px-6 py-4 whitespace-nowrap text-xs">
                            <div class="flex flex-col gap-1.5">
                                <div class="flex items-center text-gray-600">
                                    <svg class="w-3.5 h-3.5 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                    Mulai: <span class="font-medium text-heading ml-1">{{ $membership->start_date ? $membership->start_date->format('d M Y') : '-' }}</span>
                                </div>

                                {{-- Masa aktif Gym --}}
                                @if(in_array($membership->type, ['membership', 'bundle_pt_membership']))
                                    <div class="flex items-center text-gray-600">
                                        <span class="inline-block w-2 h-2 rounded-full bg-emerald-400 mr-2"></span>
                                        Gym s/d: <span class="font-medium text-emerald-600 ml-1">{{ $membership->membership_end_date ? $membership->membership_end_date->format('d M Y') : '-' }}</span>
                                    </div>
                                @endif

                                {{-- Jika Visit, tampilkan label Kunjungan Harian --}}
                                @if($membership->type === 'visit')
                                    <div class="flex items-center text-gray-600 mt-0.5">
                                        <span class="inline-block w-2 h-2 rounded-full bg-orange-400 mr-2"></span>
                                        <span class="font-medium text-orange-600 ml-1">Berlaku 1 Hari</span>
                                    </div>
                                @endif

                                {{-- Masa aktif PT --}}
                                @if(in_array($membership->type, ['pt', 'bundle_pt_membership']))
                                    <div class="flex items-center text-gray-600">
                                        <span class="inline-block w-2 h-2 rounded-full bg-indigo-400 mr-2"></span>
                                        PT s/d: <span class="font-medium text-indigo-600 ml-1">{{ $membership->pt_end_date ? $membership->pt_end_date->format('d M Y') : '-' }}</span>
                                    </div>
                                @endif
                            </div>
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
                                <span class="px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">Selesai / Sesi Habis</span>
                            @elseif ($membership->status === 'rejected')
                                <span class="px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">Rejected</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-6 py-8 text-center text-gray-500">
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