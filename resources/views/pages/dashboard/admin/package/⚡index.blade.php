<?php

namespace App\Livewire\GymPackage;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use App\Models\GymPackage;

new #[Layout('layouts::admin')] class extends Component
{
    #[Computed]
    public function packages()
    {
        return GymPackage::latest()->get();
    }

    public function delete($id)
    {
        $package = GymPackage::findOrFail($id);

        if ($package->memberships()->exists()) {
            session()->flash('error', 'Gagal dihapus: Paket ini sudah pernah dibeli oleh member. Silakan ubah status menjadi "Nonaktif" saja.');
            return; 
        }

        $package->delete();

        session()->flash('success', 'Data Paket Berhasil Dihapus.');
    }

    public function toggleStatus($id)
    {
        $package = GymPackage::find($id);
        if ($package) {
            $package->update(['is_active' => !$package->is_active]);
            
            $statusTeks = $package->is_active ? 'diaktifkan' : 'dinonaktifkan';
            session()->flash('success', "Status paket berhasil {$statusTeks}.");
        }
    }
};
?>

<div>
    <div class="flex justify-between items-center mb-6">
        <h5 class="text-xl font-semibold text-heading">Daftar Paket Membership</h5>
        
        <a href="{{ route('admin.packages.create') }}" wire:navigate class="text-white bg-brand box-border border border-transparent hover:bg-brand-strong focus:ring-4 focus:ring-brand-medium shadow-xs font-medium leading-5 rounded-md text-sm px-4 py-2.5 focus:outline-none">+ Tambah Paket</a>
    </div>

    @if (session()->has('success'))
        <div class="p-4 mb-4 text-sm text-green-800 rounded-lg bg-green-50 border border-green-200" role="alert">
            <span class="font-medium">Berhasil!</span> {{ session('success') }}
        </div>
    @endif

    @if (session()->has('error'))
        <div class="p-4 mb-4 text-sm text-red-800 rounded-lg bg-red-50 border border-red-200" role="alert">
            <span class="font-medium">Peringatan!</span> {{ session('error') }}
        </div>
    @endif

    <div class="relative overflow-x-auto bg-neutral-primary-soft shadow-xs rounded-md border border-default">
        <table class="w-full text-sm text-left rtl:text-right text-body">
            <thead class="text-sm text-body bg-neutral-secondary-medium border-b border-default-medium">
                <tr>
                    <th scope="col" class="px-6 py-3 font-medium">No</th>
                    <th scope="col" class="px-6 py-3 font-medium text-center">Tipe Layanan</th>
                    <th scope="col" class="px-6 py-3 font-medium">Nama Paket</th>
                    <th scope="col" class="px-6 py-3 font-medium text-center">Kategori</th>
                    <th scope="col" class="px-6 py-3 font-medium text-right">Harga Dasar</th>
                    <th scope="col" class="px-6 py-3 font-medium text-center">Diskon</th>
                    <th scope="col" class="px-6 py-3 font-medium text-right">Harga Akhir</th>
                    <th scope="col" class="px-6 py-3 font-medium text-center">Status</th>
                    <th scope="col" class="px-6 py-3 font-medium text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->packages as $package)
                    <tr wire:key="package-{{ $package->id }}" class="bg-neutral-primary-soft border-b border-default hover:bg-neutral-secondary-medium">
                        
                        {{-- No --}}
                        <td scope="row" class="px-7 py-4 font-medium text-heading whitespace-nowrap">
                            {{ $loop->iteration }}
                        </td>

                        {{-- Tipe Layanan & Sesi PT (DIPERBARUI) --}}
                        <td class="px-6 py-4 text-center whitespace-nowrap">
                            @if($package->type === 'pt')
                                <span class="inline-flex items-center gap-1 bg-indigo-50 text-indigo-700 text-xs font-semibold px-2.5 py-1 rounded-md border border-indigo-200">
                                    👨‍🏫 Personal Trainer
                                </span>
                                <div class="text-[11px] font-bold text-indigo-500 mt-1.5">
                                    {{ $package->pt_sessions }} Sesi
                                </div>
                            @elseif($package->type === 'visit')
                                {{-- DIPINDAHKAN KE SINI --}}
                                <span class="inline-flex items-center gap-1 bg-orange-50 text-orange-700 text-xs font-semibold px-2.5 py-1 rounded-md border border-orange-200">
                                    🎟️ Visit / Harian
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 bg-emerald-50 text-emerald-700 text-xs font-semibold px-2.5 py-1 rounded-md border border-emerald-200">
                                    🏋️ Gym Membership
                                </span>
                            @endif
                        </td>
                        
                        {{-- Nama --}}
                        <td class="px-6 py-4 font-medium text-heading whitespace-nowrap">
                            {{ $package->name }}
                        </td>

                        {{-- Kategori & Kapasitas (DIPERBARUI) --}}
                        <td class="px-6 py-4 text-center whitespace-nowrap">
                            @if($package->category === 'single')
                                <span class="bg-blue-50 text-blue-700 text-xs font-semibold px-2.5 py-1 rounded-md border border-blue-200">Single</span>
                            @elseif($package->category === 'couple')
                                <span class="bg-pink-50 text-pink-700 text-xs font-semibold px-2.5 py-1 rounded-md border border-pink-200">Couple</span>
                            @elseif($package->category === 'group')
                                <span class="bg-purple-50 text-purple-700 text-xs font-semibold px-2.5 py-1 rounded-md border border-purple-200">Group</span>
                            @else
                                <span class="bg-gray-100 text-gray-600 text-xs font-semibold px-2.5 py-1 rounded-md border border-gray-200">-</span>
                            @endif
                            
                            <div class="text-[11px] text-gray-500 mt-1.5 font-medium">
                                Max: {{ $package->max_members }} Orang
                            </div>
                        </td>
                        
                        {{-- Harga Dasar --}}
                        <td class="px-6 py-4 text-right font-medium text-gray-500 whitespace-nowrap {{ $package->discount > 0 ? 'line-through' : 'text-heading' }}">
                            Rp {{ number_format($package->price, 0, ',', '.') }}
                        </td>
                        
                        {{-- Diskon --}}
                        <td class="px-6 py-4 font-medium text-center whitespace-nowrap">
                            @if($package->discount > 0)
                                @php
                                    $percentage = ($package->price > 0) ? ($package->discount / $package->price) * 100 : 0;
                                @endphp
                                <div class="flex flex-col items-center justify-center">
                                    <span class="text-green-600 mb-1">Rp {{ number_format($package->discount, 0, ',', '.') }}</span>
                                    <span class="bg-green-100 text-green-800 text-[10px] font-bold px-1.5 py-0.5 rounded">
                                        -{{ is_float($percentage) ? round($percentage, 1) : $percentage }}%
                                    </span>
                                </div>
                            @else
                                <span class="text-gray-400">-</span>
                            @endif
                        </td>
                        
                        {{-- Harga Akhir --}}
                        @php
                            $finalPrice = $package->price - $package->discount;
                        @endphp
                        <td class="px-6 py-4 text-right font-bold text-green-700 whitespace-nowrap">
                            Rp {{ number_format($finalPrice > 0 ? $finalPrice : 0, 0, ',', '.') }}
                        </td>
                        
                        {{-- Status dengan Toggle --}}
                        <td class="px-6 py-4 text-center">
                            <button 
                                wire:click="toggleStatus({{ $package->id }})"
                                class="relative inline-flex items-center w-11 h-6 rounded-full transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-brand {{ $package->is_active ? 'bg-green-500' : 'bg-gray-300' }}"
                                title="Klik untuk mengubah status"
                            >
                                <span class="inline-block w-4 h-4 bg-white rounded-full transition-transform duration-200 transform {{ $package->is_active ? 'translate-x-6' : 'translate-x-1' }}"></span>
                            </button>
                            <div class="text-xs mt-1 font-semibold {{ $package->is_active ? 'text-green-600' : 'text-gray-500' }}">
                                {{ $package->is_active ? 'Aktif' : 'Nonaktif' }}
                            </div>
                        </td>

                        {{-- Aksi --}}
                        <td class="px-6 py-4 text-center whitespace-nowrap">
                            <a href="{{ route('admin.packages.edit', $package->id) }}" wire:navigate class="font-medium text-fg-brand hover:underline">Edit</a>
                            <button 
                                wire:click="delete({{ $package->id }})"
                                wire:confirm="Apakah Anda yakin ingin menghapus paket {{ $package->name }}?"
                                class="font-medium text-red-600 hover:underline ms-4"
                                >
                                Hapus
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="px-6 py-8 text-center text-gray-500">
                            Belum ada data paket membership.
                        </td>
                    </tr>
                @endforelse
                
            </tbody>
        </table>
    </div>
</div>