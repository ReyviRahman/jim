<?php

namespace App\Livewire\GymPackage;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use App\Models\GymPackage;

new #[Layout('layouts::admin')] class extends Component
{
    #[Validate('required|string|max:255')]
    public $name = '';

    #[Validate('required|numeric|min:0')]
    public $price = '';

    #[Validate('nullable|integer|min:1')]
    public $number_of_sessions = ''; // Kosongkan jika unlimited

    #[Validate('nullable|string')]
    public $description = '';

    public function save()
    {
        // 1. Validasi Input
        $this->validate();

        // 2. Simpan ke Database
        GymPackage::create([
            'name' => $this->name,
            'price' => $this->price,
            // Jika kosong string '', ubah menjadi null. Jika ada isinya, simpan angkanya.
            'number_of_sessions' => $this->number_of_sessions === '' ? null : $this->number_of_sessions,
            'description' => $this->description,
        ]);

        // 3. Reset Form & Kasih Notifikasi
        $this->reset(); 
        session()->flash('success', 'Paket berhasil dibuat!');
        
        return redirect()->to(route('admin.packages.index'));
    }
};
?>

<div>
    {{-- Tampilkan Pesan Sukses --}}
    @if (session()->has('success'))
        <div class="p-4 mb-4 text-sm text-green-800 rounded-lg bg-green-50" role="alert">
            <span class="font-medium">Sukses!</span> {{ session('success') }}
        </div>
    @endif

    <form wire:submit="save">
        <h5 class="text-xl font-semibold text-heading mb-6">Buat Paket Membership</h5>
        
        <div class="grid gap-6 mb-6 md:grid-cols-2">
            
            {{-- 1. Nama Paket --}}
            <div>
                <label for="name" class="block mb-2.5 text-sm font-medium text-heading">Nama Paket</label>
                <input 
                    type="text" 
                    id="name" 
                    wire:model="name"
                    class="bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-md focus:ring-brand focus:border-brand block w-full px-3 py-2.5 shadow-xs placeholder:text-body" 
                    placeholder="Contoh: Gold Member, Fat Loss Package" 
                    required 
                />
                @error('name') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
            </div>

            {{-- 2. Harga --}}
            <div>
                <label for="price" class="block mb-2.5 text-sm font-medium text-heading">Harga (Rp)</label>
                <input 
                    type="number" 
                    id="price" 
                    wire:model="price"
                    class="bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-md focus:ring-brand focus:border-brand block w-full px-3 py-2.5 shadow-xs placeholder:text-body" 
                    placeholder="Contoh: 150000" 
                    required 
                />
                @error('price') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
            </div>

            <div class="col-span-2">
                <label for="sessions" class="block mb-2.5 text-sm font-medium text-heading">Jumlah Sesi</label>
                
                <input 
                    type="number" 
                    id="sessions" 
                    wire:model="number_of_sessions"
                    class="bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-md focus:ring-brand focus:border-brand block w-full px-3 py-2.5 shadow-xs placeholder:text-body" 
                    placeholder="Contoh: 12" 
                />
                <p class="mt-1 text-xs text-gray-500">
                    * Kosongkan jika paket adalah Gym Mandiri (Sesi Unlimited).
                </p>
                @error('number_of_sessions') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
            </div>
        </div>

        <div class="mb-6">
            <label for="description" class="block mb-2.5 text-sm font-medium text-heading">Deskripsi / Fasilitas</label>
            <textarea 
                id="description" 
                wire:model="description"
                rows="3"
                class="bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-md focus:ring-brand focus:border-brand block w-full px-3 py-2.5 shadow-xs placeholder:text-body" 
                placeholder="Jelaskan detail fasilitas paket ini..." 
            ></textarea>
            @error('description') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
        </div> 

        {{-- Tombol Submit --}}
        <button 
            type="submit" 
            class="cursor-pointer text-black bg-brand box-border border border-transparent hover:bg-brand-strong focus:ring-4 focus:ring-brand-medium shadow-xs font-medium leading-5 rounded-md text-sm px-4 py-2.5 focus:outline-none disabled:opacity-50"
            wire:loading.attr="disabled"
        >
            <span wire:loading.remove>Simpan Paket</span>
            <span wire:loading>Menyimpan...</span>
        </button>
        <a href="{{ route('admin.packages.index') }}" wire:navigate class="text-body bg-neutral-secondary-medium box-border border border-default-medium hover:bg-neutral-tertiary-medium hover:text-heading focus:ring-4 focus:ring-neutral-tertiary shadow-xs font-medium leading-5 rounded-md text-sm px-4 py-2.5 focus:outline-none">Batal</a>
    </form>
</div>