<?php

namespace App\Livewire\GymPackage;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use App\Models\GymPackage;

new #[Layout('layouts::admin')] class extends Component
{
    public GymPackage $package; // Menyimpan instance model yang diedit

    #[Validate('required|string|max:255')]
    public $name = '';

    #[Validate('required|numeric|min:0')]
    public $price = '';

    #[Validate('nullable|integer|min:1')]
    public $number_of_sessions = '';

    #[Validate('nullable|string')]
    public $description = '';

    // Fungsi mount dijalankan sekali saat komponen dimuat
    public function mount(GymPackage $package)
    {
        $this->package = $package;
        
        // Isi form dengan data yang sudah ada di database
        $this->name = $package->name;
        $this->price = $package->price;
        $this->number_of_sessions = $package->number_of_sessions;
        $this->description = $package->description;
    }

    public function update()
    {
        // 1. Validasi Input
        $this->validate();

        // 2. Update Database
        $this->package->update([
            'name' => $this->name,
            'price' => $this->price,
            'number_of_sessions' => $this->number_of_sessions,
            'description' => $this->description,
        ]);

        // 3. Notifikasi & Redirect
        session()->flash('success', 'Paket berhasil diperbarui!');
        
        return redirect()->to(route('admin.packages.index'));
    }
};
?>

<div>
    {{-- Notifikasi Sukses --}}
    @if (session()->has('success'))
        <div class="p-4 mb-4 text-sm text-green-800 rounded-lg bg-green-50" role="alert">
            <span class="font-medium">Sukses!</span> {{ session('success') }}
        </div>
    @endif

    <form wire:submit="update">
        <h5 class="text-xl font-semibold text-heading mb-6">Edit Paket Membership</h5>
        
        <div class="grid gap-6 mb-6 md:grid-cols-2">
            
            {{-- 1. Nama Paket --}}
            <div>
                <label for="name" class="block mb-2.5 text-sm font-medium text-heading">Nama Paket</label>
                <input 
                    type="text" 
                    id="name" 
                    wire:model="name"
                    class="bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-md focus:ring-brand focus:border-brand block w-full px-3 py-2.5 shadow-xs placeholder:text-body" 
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
                    class="bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-md focus:ring-brand focus:border-brand block w-full px-3 py-2.5 shadow-xs" 
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
                    class="bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-md focus:ring-brand focus:border-brand block w-full px-3 py-2.5 shadow-xs" 
                    placeholder="Kosongkan jika unlimited"
                />
                @error('number_of_sessions') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
            </div>
        </div>

        <div class="mb-6">
            <label for="description" class="block mb-2.5 text-sm font-medium text-heading">Deskripsi / Fasilitas</label>
            <textarea 
                id="description" 
                wire:model="description"
                rows="3"
                class="bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-md focus:ring-brand focus:border-brand block w-full px-3 py-2.5 shadow-xs" 
            ></textarea>
            @error('description') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
        </div> 

        {{-- Tombol Submit --}}
        <div class="flex items-center gap-3">
            <button 
                type="submit" 
                class="cursor-pointer text-black bg-brand hover:bg-brand-strong focus:ring-4 focus:ring-brand-medium font-medium rounded-md text-sm px-4 py-2.5 focus:outline-none disabled:opacity-50"
                wire:loading.attr="disabled"
            >
                <span wire:loading.remove>Update Paket</span>
                <span wire:loading>Menyimpan...</span>
            </button>
            
            <a href="{{ route('admin.packages.index') }}" wire:navigate class="text-body bg-neutral-secondary-medium border border-default-medium hover:bg-neutral-tertiary-medium hover:text-heading font-medium rounded-md text-sm px-4 py-2.5 focus:outline-none">
                Batal
            </a>
        </div>
    </form>
</div>