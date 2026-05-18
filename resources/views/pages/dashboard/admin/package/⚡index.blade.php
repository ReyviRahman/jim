<?php

namespace App\Livewire\GymPackage;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use App\Models\GymPackage;
use App\Models\SalesKonsultan;

new #[Layout('layouts::admin')] class extends Component
{
    #[Computed]
    public function packages()
    {
        return GymPackage::latest()->get();
    }

    // ---- Sales Konsultan ----
    public $rentang_satu = '';
    public $rentang_dua = '';
    public $persen = '';
    public $editingId = null;
    public $showModal = false;

    public function resetForm()
    {
        $this->rentang_satu = '';
        $this->rentang_dua = '';
        $this->persen = '';
        $this->editingId = null;
        $this->showModal = false;
    }

    public function openModal()
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function edit($id)
    {
        $item = SalesKonsultan::findOrFail($id);
        $this->editingId = $item->id;
        $this->rentang_satu = $item->rentang_satu;
        $this->rentang_dua = $item->rentang_dua;
        $this->persen = $item->persen;
        $this->showModal = true;
    }

    public function save()
    {
        $this->validate([
            'rentang_satu' => 'required|string|max:255',
            'rentang_dua' => 'required|string|max:255',
            'persen' => 'required|numeric|min:0|max:100',
        ]);

        $data = [
            'rentang_satu' => $this->rentang_satu,
            'rentang_dua' => $this->rentang_dua,
            'persen' => $this->persen,
        ];

        if ($this->editingId) {
            SalesKonsultan::findOrFail($this->editingId)->update($data);
            session()->flash('success', 'Data Sales Konsultan berhasil diperbarui.');
        } else {
            SalesKonsultan::create($data);
            session()->flash('success', 'Data Sales Konsultan berhasil ditambahkan.');
        }

        $this->resetForm();
    }

    public function deleteKonsultan($id)
    {
        SalesKonsultan::findOrFail($id)->delete();
        session()->flash('success', 'Data Sales Konsultan berhasil dihapus.');
    }

    #[Computed]
    public function salesKonsultans()
    {
        return SalesKonsultan::latest()->get();
    }
    // ---- End Sales Konsultan ----

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
        <h5 class="text-xl font-semibold text-heading">Master Data Paket</h5>
        
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
                    {{-- <th scope="col" class="px-6 py-3 font-medium text-right">Harga Dasar</th> --}}
                    <th scope="col" class="px-6 py-3 font-medium text-right">Harga Dipilih</th>
                    <th scope="col" class="px-6 py-3 font-medium text-center">Diskon</th>
                    <th scope="col" class="px-6 py-3 font-medium text-right">Harga Normal</th>
                    <th scope="col" class="px-6 py-3 font-medium text-right">Harga Net</th>
                    <th scope="col" class="px-6 py-3 font-medium text-right">Harga Tidak Disarankan</th>
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
                        
                        {{-- Harga (Price) --}}
                        <td class="px-6 py-4 text-right font-bold text-heading whitespace-nowrap">
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

                        {{-- Harga Normal --}}
                        <td class="px-6 py-4 text-right font-medium text-gray-600 whitespace-nowrap">
                            @if($package->normal_price !== null)
                                Rp {{ number_format($package->normal_price, 0, ',', '.') }}
                            @else
                                <span class="text-gray-400">-</span>
                            @endif
                        </td>

                        {{-- Harga Net --}}
                        <td class="px-6 py-4 text-right font-medium text-blue-600 whitespace-nowrap">
                            @if($package->net_price !== null)
                                Rp {{ number_format($package->net_price, 0, ',', '.') }}
                            @else
                                <span class="text-gray-400">-</span>
                            @endif
                        </td>

                        {{-- Harga Tidak Disarankan --}}
                        <td class="px-6 py-4 text-right font-medium text-red-600 whitespace-nowrap">
                            @if($package->unrecommended_price !== null)
                                Rp {{ number_format($package->unrecommended_price, 0, ',', '.') }}
                            @else
                                <span class="text-gray-400">-</span>
                            @endif
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
                        <td colspan="10" class="px-6 py-8 text-center text-gray-500">
                            Belum ada data paket membership.
                        </td>
                    </tr>
                @endforelse
                
            </tbody>
        </table>
    </div>

    @if(in_array(auth()->user()->role, ['admin', 'head_coach']))
        <div class="mt-10">
            <div class="flex justify-between items-center mb-6">
                <h5 class="text-xl font-semibold text-heading">Master Data Sales Konsultan</h5>
                <button wire:click="openModal" class="text-white bg-brand box-border border border-transparent hover:bg-brand-strong focus:ring-4 focus:ring-brand-medium shadow-xs font-medium leading-5 rounded-md text-sm px-4 py-2.5 focus:outline-none">+ Tambah Rentang</button>
            </div>

            @if($showModal)
                <div x-data="{ ribuan(v) { if (!v) return ''; let s = v.toString(); if (/[a-zA-Z]/.test(s)) return s; return s.replace(/\D/g, '').replace(/\B(?=(\d{3})+(?!\d))/g, '.'); } }" class="fixed inset-0 z-50 flex items-center justify-center backdrop-blur-sm bg-black/30" wire:click.self="resetForm">
                    <div class="bg-white rounded-lg shadow-lg w-full max-w-md p-6">
                        <h3 class="text-lg font-semibold mb-4">{{ $editingId ? 'Edit' : 'Tambah' }} Rentang Bonus</h3>
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Rentang Satu</label>
                                <input type="text" x-on:input="let val = $event.target.value; if (/[a-zA-Z]/.test(val)) { $wire.set('rentang_satu', val); } else { $event.target.value = ribuan(val); $wire.set('rentang_satu', $event.target.value.replace(/\./g, '')); }" x-bind:value="ribuan($wire.rentang_satu)" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm px-3 py-2 text-sm focus:ring-brand focus:border-brand" placeholder="1000000">
                                @error('rentang_satu') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Rentang Dua</label>
                                <input type="text" x-on:input="let val = $event.target.value; if (/[a-zA-Z]/.test(val)) { $wire.set('rentang_dua', val); } else { $event.target.value = ribuan(val); $wire.set('rentang_dua', $event.target.value.replace(/\./g, '')); }" x-bind:value="ribuan($wire.rentang_dua)" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm px-3 py-2 text-sm focus:ring-brand focus:border-brand" placeholder="1000000">
                                @error('rentang_dua') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Persen (%)</label>
                                <input type="number" step="0.01" wire:model="persen" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm px-3 py-2 text-sm focus:ring-brand focus:border-brand" placeholder="10.00">
                                @error('persen') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="flex justify-end gap-2 mt-6">
                            <button wire:click="resetForm" class="px-4 py-2 text-sm text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200">Batal</button>
                            <button wire:click="save" class="px-4 py-2 text-sm text-white bg-brand rounded-md hover:bg-brand-strong">Simpan</button>
                        </div>
                    </div>
                </div>
            @endif

            <div class="relative overflow-x-auto bg-neutral-primary-soft shadow-xs rounded-md border border-default">
                <table class="w-full text-sm text-left rtl:text-right text-body">
                    <thead class="text-sm text-body bg-neutral-secondary-medium border-b border-default-medium">
                        <tr>
                            <th scope="col" class="px-6 py-3 font-medium">No</th>
                            <th scope="col" class="px-6 py-3 font-medium text-center">Rentang Satu</th>
                            <th scope="col" class="px-6 py-3 font-medium text-center">Rentang Dua</th>
                            <th scope="col" class="px-6 py-3 font-medium text-center">Persen</th>
                            <th scope="col" class="px-6 py-3 font-medium text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->salesKonsultans as $item)
                            <tr wire:key="sk-{{ $item->id }}" class="bg-neutral-primary-soft border-b border-default hover:bg-neutral-secondary-medium">
                                <td class="px-6 py-4 font-medium text-heading whitespace-nowrap">{{ $loop->iteration }}</td>
                                <td class="px-6 py-4 text-center whitespace-nowrap">{{ is_numeric($item->rentang_satu) ? number_format((float) $item->rentang_satu, 0, ",", ".") : $item->rentang_satu }}</td>
                                <td class="px-6 py-4 text-center whitespace-nowrap">{{ is_numeric($item->rentang_dua) ? number_format((float) $item->rentang_dua, 0, ",", ".") : $item->rentang_dua }}</td>
                                <td class="px-6 py-4 text-center whitespace-nowrap">{{ $item->persen }}%</td>
                                <td class="px-6 py-4 text-center whitespace-nowrap">
                                    <button wire:click="edit({{ $item->id }})" class="font-medium text-fg-brand hover:underline">Edit</button>
                                    <button wire:click="deleteKonsultan({{ $item->id }})" wire:confirm="Yakin ingin menghapus data ini?" class="font-medium text-red-600 hover:underline ms-4">Hapus</button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-8 text-center text-gray-500">Belum ada data rentang bonus.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
