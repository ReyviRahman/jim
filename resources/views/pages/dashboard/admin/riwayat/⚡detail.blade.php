<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Livewire\Attributes\Layout;
use App\Models\User;
use App\Models\Membership;

new #[Layout('layouts::admin')] class extends Component
{
    public User $user;

    public function mount(User $user)
    {
        $this->user = $user;
    }

    public function getMembershipsProperty()
    {
        return Membership::where('user_id', $this->user->id)
            ->orWhereHas('members', function ($query) {
                $query->where('user_id', $this->user->id);
            })
            ->with(['user', 'members', 'admin', 'followUp', 'followUpTwo', 'personalTrainer', 'gymPackage', 'ptPackage'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function delete($membershipId)
    {
        if (auth()->check() && auth()->user()->role !== 'admin') {
            session()->flash('error', 'Akses ditolak! Hanya Admin yang dapat menghapus data ini.');
            return;
        }

        $membership = Membership::findOrFail($membershipId);
        $membership->delete();

        session()->flash('success', 'Membership dan semua data terkait berhasil dihapus.');
    }
};
?>

<div>
    {{-- Tombol Kembali --}}
    <div class="mb-6">
        <a href="{{ route('admin.riwayat.index') }}" wire:navigate class="inline-flex items-center text-sm font-medium text-body hover:text-heading transition-colors">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
            </svg>
            Kembali ke Riwayat
        </a>
    </div>

    <div class="flex sm:flex-row flex-col justify-between items-center mb-6">
        <h5 class="text-xl font-semibold text-heading">Detail Membership - {{ $user->name }}</h5>
    </div>

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

    {{-- Info Profil User Utama --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
        
        <div class="col-span-1">
            <div class="bg-neutral-primary-soft border border-default rounded-base shadow-xs p-6 text-center">
                @if($user->photo)
                    <img class="w-32 h-32 rounded-full object-cover mx-auto mb-4 border-4 border-neutral-secondary-medium" src="{{ asset('storage/' . $user->photo) }}" alt="{{ $user->name }}">
                @else
                    <img class="w-32 h-32 rounded-full object-cover mx-auto mb-4 border-4 border-neutral-secondary-medium" src="https://ui-avatars.com/api/?name={{ urlencode($user->name) }}&background=random&size=128" alt="{{ $user->name }}">
                @endif
                
                <h2 class="text-xl font-bold text-heading">{{ $user->name }}</h2>
                <p class="text-sm text-body mb-4">{{ $user->email }}</p>

                <span class="inline-flex items-center px-3 py-1 text-sm font-medium rounded-full {{ $user->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                    <span class="w-2 h-2 mr-2 rounded-full {{ $user->is_active ? 'bg-green-500' : 'bg-red-500' }}"></span>
                    {{ $user->is_active ? 'Akun Aktif' : 'Akun Tidak Aktif' }}
                </span>
            </div>
        </div>

        <div class="col-span-1 md:col-span-2">
            <div class="bg-neutral-primary-soft border border-default rounded-base shadow-xs p-6">
                <h3 class="text-lg font-semibold text-heading mb-4 border-b border-default-medium pb-2">Informasi Personal</h3>
                
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-y-4 gap-x-6">
                    <div>
                        <label class="block text-sm font-medium text-body mb-1">Nama Lengkap</label>
                        <p class="text-base text-heading">{{ $user->name }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-body mb-1">Email</label>
                        <p class="text-base text-heading">{{ $user->email }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-body mb-1">No HP / WhatsApp</label>
                        <p class="text-base text-heading">{{ $user->phone ?? '-' }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-body mb-1">Role</label>
                        <p class="text-base text-heading capitalize">{{ str_replace('_', ' ', $user->role) }}</p>
                    </div>
                </div>
            </div>
        </div>

    </div>



    {{-- Tabel Membership --}}
    <div class="relative overflow-x-auto bg-neutral-primary-soft shadow-xs rounded-md border border-default">
        <table class="w-full text-sm text-left rtl:text-right text-body">
            <thead class="text-sm text-body bg-neutral-secondary-medium border-b border-default-medium">
                <tr>
                    <th scope="col" class="px-6 py-3 font-medium">No</th>
                    @if(auth()->check() && auth()->user()->role === 'admin')
                        <th scope="col" class="px-6 py-3 font-medium text-center">Aksi</th>
                    @endif
                    <th scope="col" class="px-6 py-3 font-medium">Member</th>
                    <th scope="col" class="px-6 py-3 font-medium">Program / Paket</th>
                    <th scope="col" class="px-6 py-3 font-medium text-right">Total Bayar</th>
                    <th scope="col" class="px-6 py-3 font-medium">Masa Aktif</th>
                    <th scope="col" class="px-6 py-3 font-medium text-center">Status</th>
                    <th scope="col" class="px-6 py-3 font-medium text-center">Admin Follow Up</th>
                    <th scope="col" class="px-6 py-3 font-medium text-center">Sales Follow Up</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->memberships as $membership)
                    <tr wire:key="{{ $membership->id }}" class="bg-neutral-primary-soft border-b border-default hover:bg-neutral-secondary-medium">
                        
                        {{-- Nomor Urut --}}
                        <td class="px-6 py-4 font-medium text-heading whitespace-nowrap">
                            {{ $loop->iteration }}
                        </td>

                        @if(auth()->check() && auth()->user()->role === 'admin')
                            {{-- AKSI --}}
                            <td class="px-6 py-4 text-center whitespace-nowrap">
                                <div class="flex items-center justify-center gap-2">
                                    <a href="{{ route('admin.membership.edit', $membership->id) }}" wire:navigate class="inline-flex items-center justify-center text-fg-brand hover:text-brand-strong" title="Edit">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                                        </svg>
                                    </a>
                                    <button
                                        wire:click="delete({{ $membership->id }})"
                                        wire:confirm="Apakah Anda yakin ingin menghapus membership ini?"
                                        class="inline-flex items-center justify-center text-red-600 hover:text-red-800"
                                        title="Hapus"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        @endif

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
                        </td>

                        {{-- INFO PROGRAM / PAKET & SESI COACH (DIGABUNG) --}}
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
                                        
                                        <div class="flex items-center gap-3 mt-1">
                                            <div class="text-xs text-gray-500">
                                                Coach: <span class="font-medium text-gray-700">{{ $membership->personalTrainer->name ?? '-' }}</span>
                                            </div>
                                            
                                            {{-- Informasi Sesi Coach Pindahan --}}
                                            @if ($membership->total_sessions)
                                                <div class="text-xs text-gray-500 border-l border-gray-300 pl-3">
                                                    Sisa Sesi: 
                                                    <span class="font-bold {{ $membership->remaining_sessions <= 2 ? 'text-red-600' : 'text-green-600' }}">
                                                        {{ $membership->remaining_sessions }}
                                                    </span> 
                                                    <span class="text-gray-400">/ {{ $membership->total_sessions }}</span>
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                @endif
                            </div>
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

                            {{-- Logika Penentuan Label Harga Sesuai Rentang --}}
                            @if(auth()->check() && auth()->user()->role === 'admin')
                                @php
                                    $priceLabelData = $membership->getPriceLabel();
                                @endphp

                                @if($priceLabelData)
                                    <div class="mt-1 flex justify-end">
                                        <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full {{ $priceLabelData['color'] }}">
                                            {{ $priceLabelData['label'] }}
                                        </span>
                                    </div>
                                @endif
                            @endif
                        </td>
                        {{-- Masa Aktif --}}
                        <td class="px-6 py-4 whitespace-nowrap text-xs">
                            <div class="flex flex-col gap-1.5">
                                <div class="flex items-center text-gray-600">
                                    <svg class="w-3.5 h-3.5 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                    Mulai: <span class="font-medium text-heading ml-1">{{ $membership->start_date ? $membership->start_date->format('d M Y') : 'BELUM AKTIF' }}</span>
                                </div>

                                {{-- Masa aktif Gym --}}
                                @if(in_array($membership->type, ['membership', 'bundle_pt_membership']))
                                    <div class="flex items-center text-gray-600">
                                        <span class="inline-block w-2 h-2 rounded-full bg-emerald-400 mr-2"></span>
                                        Gym s/d: <span class="font-medium text-emerald-600 ml-1">{{ $membership->membership_end_date ? $membership->membership_end_date->format('d M Y') : 'BELUM AKTIF' }}</span>
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
                                        PT s/d: <span class="font-medium text-indigo-600 ml-1">{{ $membership->pt_end_date ? $membership->pt_end_date->format('d M Y') : 'BELUM AKTIF' }}</span>
                                    </div>
                                @endif
                            </div>
                        </td>

                        <td class="px-6 py-4 whitespace-nowrap text-center">
                            @php
                                $statusColor = match($membership->status) {
                                    'active' => 'bg-green-100 text-green-800',
                                    'pending' => 'bg-yellow-100 text-yellow-800',
                                    'expired' => 'bg-red-100 text-red-800',
                                    'cancelled' => 'bg-gray-100 text-gray-800',
                                    default => 'bg-blue-100 text-blue-800'
                                };
                                $statusLabel = match($membership->status) {
                                    'active' => 'Aktif',
                                    'pending' => 'Menunggu',
                                    'expired' => 'Kadaluarsa',
                                    'cancelled' => 'Dibatalkan',
                                    default => ucfirst($membership->status)
                                };
                            @endphp
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $statusColor }}">
                                {{ $statusLabel }}
                            </span>
                        </td>

                        <td class="px-6 py-4 font-medium text-heading whitespace-nowrap text-center">
                            <span class="font-semibold">{{ $membership->followUp->name ?? '-' }}</span>
                        </td>
                        <td class="px-6 py-4 font-medium text-heading whitespace-nowrap text-center">
                            <span class="font-semibold">{{ $membership->followUpTwo->name ?? '-' }}</span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ auth()->check() && auth()->user()->role === 'admin' ? '9' : '8' }}" class="px-6 py-8 text-center text-gray-500">
                            Belum ada riwayat membership untuk user ini.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>