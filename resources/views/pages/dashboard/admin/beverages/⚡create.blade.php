<?php

namespace App\Livewire\Pages\Dashboard\Admin\Beverages;

use App\Models\Beverage;
use Livewire\Component;
use Livewire\Attributes\Validate;
use Livewire\Attributes\Layout;

new #[Layout('layouts::admin')] class extends Component
{
    #[Validate('required|min:2')]
    public $nama_produk = '';

    #[Validate('required|integer|min:0')]
    public $harga_modal = '';

    #[Validate('required|integer|min:0')]
    public $harga_jual = '';

    public function store()
    {
        $this->validate();

        Beverage::create([
            'nama_produk' => $this->nama_produk,
            'harga_modal' => $this->harga_modal,
            'harga_jual' => $this->harga_jual,
            'stok_sekarang' => 0,
        ]);

        session()->flash('success', 'Minuman berhasil ditambahkan.');

        return $this->redirectRoute('admin.beverages.index', navigate: true);
    }
};
?>

<div>
    <h1 class="text-3xl text-center font-semibold mb-6">Tambah Minuman</h1>

    @if (session()->has('success'))
        <div class="p-4 mb-4 text-sm text-green-800 rounded-lg bg-green-50" role="alert">
            <span class="font-medium">Sukses!</span> {{ session('success') }}
        </div>
    @endif

    <form wire:submit.prevent="store" class="max-w-xl mx-auto">
        <div class="mb-4">
            <label for="nama_produk" class="block mb-2.5 text-sm font-medium text-heading">Nama Produk</label>
            <input type="text" id="nama_produk" wire:model="nama_produk"
                class="block w-full px-3 py-2.5 bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base focus:ring-brand focus:border-brand shadow-xs placeholder:text-body"
                placeholder="Contoh: Aqua 600ml">
            @error('nama_produk') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
        </div>

        <div class="mb-4">
            <label for="harga_modal" class="block mb-2.5 text-sm font-medium text-heading">Harga Modal (Rp)</label>
            <input type="number" id="harga_modal" wire:model="harga_modal" min="0"
                class="block w-full px-3 py-2.5 bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base focus:ring-brand focus:border-brand shadow-xs placeholder:text-body"
                placeholder="Contoh: 3500">
            @error('harga_modal') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
        </div>

        <div class="mb-6">
            <label for="harga_jual" class="block mb-2.5 text-sm font-medium text-heading">Harga Jual (Rp)</label>
            <input type="number" id="harga_jual" wire:model="harga_jual" min="0"
                class="block w-full px-3 py-2.5 bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base focus:ring-brand focus:border-brand shadow-xs placeholder:text-body"
                placeholder="Contoh: 5000">
            @error('harga_jual') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
        </div>

        <div class="flex justify-end gap-3">
            <a href="{{ route('admin.beverages.index') }}" wire:navigate class="px-4 py-2.5 text-heading bg-neutral-secondary-medium border border-default-medium rounded-md hover:bg-neutral-secondary-strong font-medium text-sm">
                Batal
            </a>
            <button type="submit" class="px-4 py-2.5 text-white bg-brand hover:bg-brand-strong rounded-md font-medium text-sm focus:outline-none">
                Simpan
            </button>
        </div>
    </form>
</div>