<?php

namespace App\Livewire\Pages\Dashboard\Admin\SesiPt;

use App\Models\Period;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;

new #[Layout('layouts::admin')] class extends Component
{
    public $search = '';

    // Modal states
    public $showModal = false;
    public $modalMode = 'create'; // 'create' or 'edit'
    public $editingId = null;
    public $name = '';

    public function openCreateModal()
    {
        $this->reset(['name', 'editingId']);
        $this->modalMode = 'create';
        $this->showModal = true;
    }

    public function openEditModal($id)
    {
        $period = Period::findOrFail($id);
        $this->editingId = $id;
        $this->name = $period->name;
        $this->modalMode = 'edit';
        $this->showModal = true;
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->reset(['name', 'editingId', 'modalMode']);
        $this->resetValidation();
    }

    public function save()
    {
        $this->validate([
            'name' => 'required|string|max:255',
        ]);

        if ($this->modalMode === 'create') {
            Period::create(['name' => $this->name]);
            session()->flash('success', 'Periode berhasil ditambahkan.');
        } else {
            $period = Period::findOrFail($this->editingId);
            $period->update(['name' => $this->name]);
            session()->flash('success', 'Periode berhasil diperbarui.');
        }

        $this->closeModal();
    }

    public function delete($id)
    {
        $period = Period::findOrFail($id);
        $period->delete();
        session()->flash('success', 'Periode berhasil dihapus.');
    }

    #[Computed]
    public function periods()
    {
        return Period::when($this->search, function ($query) {
            $query->where('name', 'like', '%' . $this->search . '%');
        })
        ->latest()
        ->get();
    }
};
?>

<div>
    <div class="flex sm:flex-row flex-col justify-between items-center mb-6">
        <h5 class="text-xl font-semibold text-heading">Master Data Periode</h5>
        @if(auth()->check() && auth()->user()->role === 'admin')
            <button type="button" wire:click="openCreateModal" class="text-white bg-brand box-border border border-transparent hover:bg-brand-strong focus:ring-4 focus:ring-brand-medium shadow-xs font-medium leading-5 rounded-md text-sm px-4 py-2.5 focus:outline-none">
                + Tambah Periode
            </button>
        @endif
    </div>

    @if (session()->has('success'))
        <div x-data="{ show: true }" x-show="show" x-transition.duration.300ms class="mb-6 flex items-center justify-between p-4 text-sm text-emerald-800 border border-emerald-200 rounded-md bg-emerald-50 shadow-xs">
            <div class="flex items-center gap-2">
                <svg class="w-5 h-5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                <span class="font-medium">{{ session('success') }}</span>
            </div>
            <button @click="show = false" type="button" class="text-emerald-600 hover:text-emerald-900 focus:outline-none">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
    @endif

    @if (session()->has('error'))
        <div x-data="{ show: true }" x-show="show" x-transition.duration.300ms class="mb-6 flex items-center justify-between p-4 text-sm text-red-800 border border-red-200 rounded-md bg-red-50 shadow-xs">
            <div class="flex items-center gap-2">
                <svg class="w-5 h-5 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"></path></svg>
                <span class="font-medium">{{ session('error') }}</span>
            </div>
            <button @click="show = false" type="button" class="text-red-600 hover:text-red-900 focus:outline-none">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
    @endif

    <div class="bg-neutral-primary-soft shadow-xs rounded-md border border-default">
        <div class="p-4 flex flex-col md:flex-row items-center justify-between gap-4">
            <div class="flex-1 w-full md:w-auto">
                <input type="text" wire:model.live.debounce.300ms="search" class="block w-full ps-9 pe-3 py-2.5 bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base focus:ring-brand focus:border-brand shadow-xs placeholder:text-body" placeholder="Cari periode...">
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left rtl:text-right text-body">
                <thead class="text-sm text-body bg-neutral-secondary-medium border-b border-default-medium">
                    <tr>
                        <th scope="col" class="px-6 py-3 font-medium">No</th>
                        <th scope="col" class="px-6 py-3 font-medium">Nama Periode</th>
                        <th scope="col" class="px-6 py-3 font-medium text-center">Dibuat</th>
                        @if(auth()->check() && auth()->user()->role === 'admin')
                            <th scope="col" class="px-6 py-3 font-medium text-center">Aksi</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->periods as $period)
                        <tr wire:key="period-{{ $period->id }}" class="bg-neutral-primary-soft border-b border-default hover:bg-neutral-secondary-medium">
                            <td class="px-6 py-4 font-medium text-heading whitespace-nowrap">
                                {{ $loop->iteration }}
                            </td>
                            <td class="px-6 py-4 font-medium text-heading whitespace-nowrap">
                                {{ $period->name }}
                            </td>
                            <td class="px-6 py-4 text-center whitespace-nowrap">
                                {{ $period->created_at->format('d M Y') }}
                            </td>
                            @if(auth()->check() && auth()->user()->role === 'admin')
                                <td class="px-6 py-4 text-center whitespace-nowrap">
                                    <div class="flex items-center justify-center gap-2">
                                        <button type="button" wire:click="openEditModal({{ $period->id }})" class="inline-flex items-center gap-1 px-3 py-2 text-sm font-medium text-yellow-700 bg-yellow-50 border border-yellow-200 rounded-md hover:bg-yellow-100 focus:ring-2 focus:ring-yellow-300 transition-colors">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"></path></svg>
                                            Edit
                                        </button>
                                        <button type="button" wire:click="delete({{ $period->id }})" wire:confirm="Apakah Anda yakin ingin menghapus periode '{{ $period->name }}'?" class="inline-flex items-center gap-1 px-3 py-2 text-sm font-medium text-red-700 bg-red-50 border border-red-200 rounded-md hover:bg-red-100 focus:ring-2 focus:ring-red-300 transition-colors">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"></path><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path></svg>
                                            Hapus
                                        </button>
                                    </div>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ auth()->check() && auth()->user()->role === 'admin' ? 4 : 3 }}" class="px-6 py-8 text-center text-gray-500">
                                Belum ada data periode.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Modal Create/Edit --}}
    @if($showModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm" wire:click.self="closeModal">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-md mx-4 p-6" @click.stop>
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold text-gray-900">
                    {{ $modalMode === 'create' ? 'Tambah Periode' : 'Edit Periode' }}
                </h3>
                <button type="button" wire:click="closeModal" class="text-gray-400 hover:text-gray-600">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>

            <div class="space-y-4">
                <div>
                    <label class="block mb-1 text-sm font-medium text-gray-700">Nama Periode</label>
                    <input type="text" wire:model="name" class="block w-full px-3 py-2 bg-white border border-gray-300 rounded-md text-sm focus:ring-brand focus:border-brand" placeholder="Masukkan nama periode...">
                    @error('name') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                </div>
            </div>

            <div class="flex gap-3 mt-6">
                <button type="button" wire:click="closeModal" class="flex-1 px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-gray-400">
                    Batal
                </button>
                <button type="button" wire:click="save" wire:loading.attr="disabled" class="flex-1 px-4 py-2 text-sm font-medium text-white bg-brand rounded-md hover:bg-brand-strong focus:outline-none focus:ring-2 focus:ring-brand-medium disabled:opacity-50">
                    <span wire:loading.remove wire:target="save">Simpan</span>
                    <span wire:loading wire:target="save">Menyimpan...</span>
                </button>
            </div>
        </div>
    </div>
    @endif
</div>
