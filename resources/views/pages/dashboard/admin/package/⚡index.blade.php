<?php

namespace App\Livewire\GymPackage;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use App\Models\GymPackage;

new #[Layout('layouts::admin')] class extends Component
{
    // Ambil data paket (Computed agar hemat database)
    #[Computed]
    public function packages()
    {
        return GymPackage::latest()->get();
    }

    // Fitur: Hapus Paket (Safe Delete)
    public function delete($id)
    {
        $package = GymPackage::findOrFail($id);

        // Proteksi: Cek apakah paket ini sudah pernah dibeli oleh member
        if ($package->memberships()->exists()) {
            session()->flash('error', 'Gagal dihapus: Paket ini sudah pernah dibeli oleh member. Silakan ubah status menjadi "Nonaktif" saja.');
            return; // Hentikan proses eksekusi
        }

        // Jika aman (belum ada yang beli), hapus data
        $package->delete();

        // flash message sukses
        session()->flash('success', 'Data Paket Berhasil Dihapus.');
    }

    // Fitur: Ubah Status (Aktif/Tidak)
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

    {{-- Area Alert / Notifikasi --}}
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
                    <th scope="col" class="px-6 py-3 font-medium">Nama Paket</th>
                    <th scope="col" class="px-6 py-3 font-medium">Harga Dasar</th>
                    <th scope="col" class="px-6 py-3 font-medium text-center">Diskon</th>
                    <th scope="col" class="px-6 py-3 font-medium">Harga Akhir</th>
                    <th scope="col" class="px-6 py-3 font-medium text-center">Status</th>
                    <th scope="col" class="px-6 py-3 font-medium">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->packages as $package)
                    <tr wire:key="package-{{ $package->id }}" class="bg-neutral-primary-soft border-b border-default hover:bg-neutral-secondary-medium">
                        <td scope="row" class="px-7 py-4 font-medium text-heading whitespace-nowrap">
                            {{ $loop->iteration }}
                        </td>
                        <td class="px-6 py-4 font-medium text-heading whitespace-nowrap">
                            {{ $package->name }}
                        </td>
                        
                        {{-- Harga Dasar --}}
                        <td class="px-6 py-4 font-medium text-gray-500 whitespace-nowrap {{ $package->discount_percentage > 0 ? 'line-through' : 'text-heading' }}">
                            Rp {{ number_format($package->price, 0, ',', '.') }}
                        </td>
                        
                        {{-- Diskon --}}
                        <td class="px-6 py-4 font-medium text-center whitespace-nowrap">
                            @if($package->discount_percentage > 0)
                                <span class="bg-green-100 text-green-800 text-xs font-semibold px-2.5 py-0.5 rounded-full">
                                    {{ (float) $package->discount_percentage }}%
                                </span>
                            @else
                                <span class="text-gray-400">-</span>
                            @endif
                        </td>
                        
                        {{-- Harga Akhir (Dihitung langsung di blade) --}}
                        @php
                            $discountAmount = $package->price * ($package->discount_percentage / 100);
                            $finalPrice = $package->price - $discountAmount;
                        @endphp
                        <td class="px-6 py-4 font-bold text-green-700 whitespace-nowrap">
                            Rp {{ number_format($finalPrice, 0, ',', '.') }}
                        </td>
                        
                        {{-- Kolom Status dengan Toggle Switch UI --}}
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

                        <td class="px-6 py-4 whitespace-nowrap">
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
                        <td colspan="7" class="px-6 py-8 text-center text-gray-500">
                            Belum ada data paket membership.
                        </td>
                    </tr>
                @endforelse
                
            </tbody>
        </table>
    </div>
</div>