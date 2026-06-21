<?php

namespace App\Livewire\RentangBonus;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use App\Models\SalesKonsultan;
use App\Models\KasirKonsultan;
use App\Models\CoachKonsultan;

new #[Layout('layouts::admin')] class extends Component
{
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

    // ---- Kasir Konsultan ----
    public $rentang_satu_kasir = '';
    public $rentang_dua_kasir = '';
    public $persen_kasir = '';
    public $editingKasirId = null;
    public $showKasirModal = false;

    public function resetKasirForm()
    {
        $this->rentang_satu_kasir = '';
        $this->rentang_dua_kasir = '';
        $this->persen_kasir = '';
        $this->editingKasirId = null;
        $this->showKasirModal = false;
    }

    public function openKasirModal()
    {
        $this->resetKasirForm();
        $this->showKasirModal = true;
    }

    public function editKasir($id)
    {
        $item = KasirKonsultan::findOrFail($id);
        $this->editingKasirId = $item->id;
        $this->rentang_satu_kasir = $item->rentang_satu;
        $this->rentang_dua_kasir = $item->rentang_dua;
        $this->persen_kasir = $item->persen;
        $this->showKasirModal = true;
    }

    public function saveKasir()
    {
        $this->validate([
            'rentang_satu_kasir' => 'required|string|max:255',
            'rentang_dua_kasir' => 'required|string|max:255',
            'persen_kasir' => 'required|numeric|min:0|max:100',
        ]);

        $data = [
            'rentang_satu' => $this->rentang_satu_kasir,
            'rentang_dua' => $this->rentang_dua_kasir,
            'persen' => $this->persen_kasir,
        ];

        if ($this->editingKasirId) {
            KasirKonsultan::findOrFail($this->editingKasirId)->update($data);
            session()->flash('success', 'Data Kasir Konsultan berhasil diperbarui.');
        } else {
            KasirKonsultan::create($data);
            session()->flash('success', 'Data Kasir Konsultan berhasil ditambahkan.');
        }

        $this->resetKasirForm();
    }

    public function deleteKasirKonsultan($id)
    {
        KasirKonsultan::findOrFail($id)->delete();
        session()->flash('success', 'Data Kasir Konsultan berhasil dihapus.');
    }

    #[Computed]
    public function kasirKonsultans()
    {
        return KasirKonsultan::latest()->get();
    }
    // ---- End Kasir Konsultan ----

    // ---- Coach Konsultan ----
    public $rentang_satu_coach = '';
    public $rentang_dua_coach = '';
    public $persen_coach = '';
    public $editingCoachId = null;
    public $showCoachModal = false;

    public function resetCoachForm()
    {
        $this->rentang_satu_coach = '';
        $this->rentang_dua_coach = '';
        $this->persen_coach = '';
        $this->editingCoachId = null;
        $this->showCoachModal = false;
    }

    public function openCoachModal()
    {
        $this->resetCoachForm();
        $this->showCoachModal = true;
    }

    public function editCoach($id)
    {
        $item = CoachKonsultan::findOrFail($id);
        $this->editingCoachId = $item->id;
        $this->rentang_satu_coach = $item->rentang_satu;
        $this->rentang_dua_coach = $item->rentang_dua;
        $this->persen_coach = $item->persen;
        $this->showCoachModal = true;
    }

    public function saveCoach()
    {
        $this->validate([
            'rentang_satu_coach' => 'required|string|max:255',
            'rentang_dua_coach' => 'required|string|max:255',
            'persen_coach' => 'required|numeric|min:0|max:100',
        ]);

        $data = [
            'rentang_satu' => $this->rentang_satu_coach,
            'rentang_dua' => $this->rentang_dua_coach,
            'persen' => $this->persen_coach,
        ];

        if ($this->editingCoachId) {
            CoachKonsultan::findOrFail($this->editingCoachId)->update($data);
            session()->flash('success', 'Data Coach Konsultan berhasil diperbarui.');
        } else {
            CoachKonsultan::create($data);
            session()->flash('success', 'Data Coach Konsultan berhasil ditambahkan.');
        }

        $this->resetCoachForm();
    }

    public function deleteCoachKonsultan($id)
    {
        CoachKonsultan::findOrFail($id)->delete();
        session()->flash('success', 'Data Coach Konsultan berhasil dihapus.');
    }

    #[Computed]
    public function coachKonsultans()
    {
        return CoachKonsultan::latest()->get();
    }
    // ---- End Coach Konsultan ----
};
?>

<div>
    <div class="flex justify-between items-center mb-6">
        <h5 class="text-xl font-semibold text-heading">Master Rentang Bonus</h5>
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

    {{-- Sales Konsultan --}}
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

    {{-- Kasir Konsultan --}}
    <div class="mt-10">
        <div class="flex justify-between items-center mb-6">
            <h5 class="text-xl font-semibold text-heading">Master Data Admin Konsultan</h5>
            <button wire:click="openKasirModal" class="text-white bg-brand box-border border border-transparent hover:bg-brand-strong focus:ring-4 focus:ring-brand-medium shadow-xs font-medium leading-5 rounded-md text-sm px-4 py-2.5 focus:outline-none">+ Tambah Rentang</button>
        </div>

        @if($showKasirModal)
            <div x-data="{ ribuan(v) { if (!v) return ''; let s = v.toString(); if (/[a-zA-Z]/.test(s)) return s; return s.replace(/\D/g, '').replace(/\B(?=(\d{3})+(?!\d))/g, '.'); } }" class="fixed inset-0 z-50 flex items-center justify-center backdrop-blur-sm bg-black/30" wire:click.self="resetKasirForm">
                <div class="bg-white rounded-lg shadow-lg w-full max-w-md p-6">
                    <h3 class="text-lg font-semibold mb-4">{{ $editingKasirId ? 'Edit' : 'Tambah' }} Rentang Bonus Kasir</h3>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Rentang Satu</label>
                            <input type="text" x-on:input="let val = $event.target.value; if (/[a-zA-Z]/.test(val)) { $wire.set('rentang_satu_kasir', val); } else { $event.target.value = ribuan(val); $wire.set('rentang_satu_kasir', $event.target.value.replace(/\./g, '')); }" x-bind:value="ribuan($wire.rentang_satu_kasir)" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm px-3 py-2 text-sm focus:ring-brand focus:border-brand" placeholder="1000000">
                            @error('rentang_satu_kasir') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Rentang Dua</label>
                            <input type="text" x-on:input="let val = $event.target.value; if (/[a-zA-Z]/.test(val)) { $wire.set('rentang_dua_kasir', val); } else { $event.target.value = ribuan(val); $wire.set('rentang_dua_kasir', $event.target.value.replace(/\./g, '')); }" x-bind:value="ribuan($wire.rentang_dua_kasir)" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm px-3 py-2 text-sm focus:ring-brand focus:border-brand" placeholder="1000000">
                            @error('rentang_dua_kasir') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Persen (%)</label>
                            <input type="number" step="0.01" wire:model="persen_kasir" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm px-3 py-2 text-sm focus:ring-brand focus:border-brand" placeholder="10.00">
                            @error('persen_kasir') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>
                    </div>
                    <div class="flex justify-end gap-2 mt-6">
                        <button wire:click="resetKasirForm" class="px-4 py-2 text-sm text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200">Batal</button>
                        <button wire:click="saveKasir" class="px-4 py-2 text-sm text-white bg-brand rounded-md hover:bg-brand-strong">Simpan</button>
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
                    @forelse ($this->kasirKonsultans as $item)
                        <tr wire:key="kk-{{ $item->id }}" class="bg-neutral-primary-soft border-b border-default hover:bg-neutral-secondary-medium">
                            <td class="px-6 py-4 font-medium text-heading whitespace-nowrap">{{ $loop->iteration }}</td>
                            <td class="px-6 py-4 text-center whitespace-nowrap">{{ is_numeric($item->rentang_satu) ? number_format((float) $item->rentang_satu, 0, ",", ".") : $item->rentang_satu }}</td>
                            <td class="px-6 py-4 text-center whitespace-nowrap">{{ is_numeric($item->rentang_dua) ? number_format((float) $item->rentang_dua, 0, ",", ".") : $item->rentang_dua }}</td>
                            <td class="px-6 py-4 text-center whitespace-nowrap">{{ $item->persen }}%</td>
                            <td class="px-6 py-4 text-center whitespace-nowrap">
                                <button wire:click="editKasir({{ $item->id }})" class="font-medium text-fg-brand hover:underline">Edit</button>
                                <button wire:click="deleteKasirKonsultan({{ $item->id }})" wire:confirm="Yakin ingin menghapus data ini?" class="font-medium text-red-600 hover:underline ms-4">Hapus</button>
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

    {{-- Coach Konsultan --}}
    <div class="mt-10">
        <div class="flex justify-between items-center mb-6">
            <h5 class="text-xl font-semibold text-heading">Master Data Coach Konsultan</h5>
            <button wire:click="openCoachModal" class="text-white bg-brand box-border border border-transparent hover:bg-brand-strong focus:ring-4 focus:ring-brand-medium shadow-xs font-medium leading-5 rounded-md text-sm px-4 py-2.5 focus:outline-none">+ Tambah Rentang</button>
        </div>

        @if($showCoachModal)
            <div x-data="{ ribuan(v) { if (!v) return ''; let s = v.toString(); if (/[a-zA-Z]/.test(s)) return s; return s.replace(/\D/g, '').replace(/\B(?=(\d{3})+(?!\d))/g, '.'); } }" class="fixed inset-0 z-50 flex items-center justify-center backdrop-blur-sm bg-black/30" wire:click.self="resetCoachForm">
                <div class="bg-white rounded-lg shadow-lg w-full max-w-md p-6">
                    <h3 class="text-lg font-semibold mb-4">{{ $editingCoachId ? 'Edit' : 'Tambah' }} Rentang Bonus Coach</h3>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Rentang Satu</label>
                            <input type="text" x-on:input="let val = $event.target.value; if (/[a-zA-Z]/.test(val)) { $wire.set('rentang_satu_coach', val); } else { $event.target.value = ribuan(val); $wire.set('rentang_satu_coach', $event.target.value.replace(/\./g, '')); }" x-bind:value="ribuan($wire.rentang_satu_coach)" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm px-3 py-2 text-sm focus:ring-brand focus:border-brand" placeholder="1000000">
                            @error('rentang_satu_coach') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Rentang Dua</label>
                            <input type="text" x-on:input="let val = $event.target.value; if (/[a-zA-Z]/.test(val)) { $wire.set('rentang_dua_coach', val); } else { $event.target.value = ribuan(val); $wire.set('rentang_dua_coach', $event.target.value.replace(/\./g, '')); }" x-bind:value="ribuan($wire.rentang_dua_coach)" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm px-3 py-2 text-sm focus:ring-brand focus:border-brand" placeholder="1000000">
                            @error('rentang_dua_coach') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Persen (%)</label>
                            <input type="number" step="0.01" wire:model="persen_coach" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm px-3 py-2 text-sm focus:ring-brand focus:border-brand" placeholder="10.00">
                            @error('persen_coach') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>
                    </div>
                    <div class="flex justify-end gap-2 mt-6">
                        <button wire:click="resetCoachForm" class="px-4 py-2 text-sm text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200">Batal</button>
                        <button wire:click="saveCoach" class="px-4 py-2 text-sm text-white bg-brand rounded-md hover:bg-brand-strong">Simpan</button>
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
                    @forelse ($this->coachKonsultans as $item)
                        <tr wire:key="ck-{{ $item->id }}" class="bg-neutral-primary-soft border-b border-default hover:bg-neutral-secondary-medium">
                            <td class="px-6 py-4 font-medium text-heading whitespace-nowrap">{{ $loop->iteration }}</td>
                            <td class="px-6 py-4 text-center whitespace-nowrap">{{ is_numeric($item->rentang_satu) ? number_format((float) $item->rentang_satu, 0, ",", ".") : $item->rentang_satu }}</td>
                            <td class="px-6 py-4 text-center whitespace-nowrap">{{ is_numeric($item->rentang_dua) ? number_format((float) $item->rentang_dua, 0, ",", ".") : $item->rentang_dua }}</td>
                            <td class="px-6 py-4 text-center whitespace-nowrap">{{ $item->persen }}%</td>
                            <td class="px-6 py-4 text-center whitespace-nowrap">
                                <button wire:click="editCoach({{ $item->id }})" class="font-medium text-fg-brand hover:underline">Edit</button>
                                <button wire:click="deleteCoachKonsultan({{ $item->id }})" wire:confirm="Yakin ingin menghapus data ini?" class="font-medium text-red-600 hover:underline ms-4">Hapus</button>
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
</div>
