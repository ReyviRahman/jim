<?php

namespace App\Livewire\GymPackage;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Attributes\Computed; 
use App\Models\GymPackage;

new #[Layout('layouts::admin')] class extends Component
{
    #[Validate('required|string|max:255')]
    public $name = '';

    #[Validate('required|numeric|min:0')]
    public $price = '';

    // PERUBAHAN: Validasi untuk nominal diskon (bukan persentase)
    // Minimal 0, dan maksimal tidak boleh melebihi harga paket.
    #[Validate('nullable|numeric|min:0')]
    public $discount = ''; 

    #[Validate('nullable|string')]
    public $description = '';

    // Menghitung harga akhir secara real-time
    #[Computed]
    public function finalPrice()
    {
        $basePrice = (float) ($this->price ?: 0);
        $discountAmount = (float) ($this->discount ?: 0);
        
        // Pastikan diskon tidak lebih besar dari harga
        if ($discountAmount > $basePrice) {
            $discountAmount = $basePrice;
        }
        if ($discountAmount < 0) {
            $discountAmount = 0;
        }

        return $basePrice - $discountAmount;
    }

    public function save()
    {
        // Validasi kustom: Diskon tidak boleh lebih besar dari harga
        if ((float) $this->discount > (float) $this->price) {
            $this->addError('discount', 'Diskon tidak boleh lebih besar dari harga paket.');
            return;
        }

        // 1. Validasi Input
        $this->validate();

        // 2. Simpan ke Database
        GymPackage::create([
            'name' => $this->name,
            'price' => $this->price,
            // PERUBAHAN: Simpan ke kolom 'discount' (nominal)
            'discount' => $this->discount === '' ? 0 : $this->discount,
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
                    placeholder="Contoh: Paket Bulking, Paket Harian" 
                    required 
                />
                @error('name') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
            </div>

            {{-- 2. Harga (Gunakan wire:model.live) --}}
            <div>
                <label for="price" class="block mb-2.5 text-sm font-medium text-heading">Harga (Rp)</label>
                <input 
                    type="number" 
                    id="price" 
                    wire:model.live="price"
                    class="bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-md focus:ring-brand focus:border-brand block w-full px-3 py-2.5 shadow-xs placeholder:text-body" 
                    placeholder="Contoh: 150000" 
                    required 
                />
                @error('price') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
            </div>

            {{-- 3. Diskon Nominal (Gunakan wire:model.live) --}}
            <div class="col-span-2 md:col-span-1">
                <label for="discount" class="block mb-2.5 text-sm font-medium text-heading">Diskon Paket (Rp)</label>
                <div class="relative">
                    <div class="absolute inset-y-0 start-0 flex items-center pl-3 pointer-events-none">
                        <span class="text-gray-500 text-sm">Rp</span>
                    </div>
                    <input 
                        type="number" 
                        id="discount" 
                        wire:model.live="discount"
                        class="bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-md focus:ring-brand focus:border-brand block w-full pl-9 pr-3 py-2.5 shadow-xs placeholder:text-body" 
                        placeholder="Contoh: 25000"
                    />
                </div>
                <p class="mt-1 text-xs text-gray-500">Kosongkan jika tidak ada diskon.</p>
                @error('discount') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
            </div>
            
            {{-- 4. Preview Harga Akhir --}}
            {{-- 4. Preview Harga Akhir --}}
            <div class="col-span-2 md:col-span-1 flex items-end">
                {{-- Pastikan price lebih dari 0 untuk menghindari error pembagian dengan nol --}}
                @if($price > 0 && $discount > 0)
                    @php
                        // Hitung persentase diskon
                        $percentage = ($discount / $price) * 100;
                    @endphp
                    <div class="w-full p-4 bg-green-50 border border-green-200 rounded-md">
                        <div class="flex justify-between items-center mb-1">
                            <p class="text-xs text-green-700 font-medium">Harga Setelah Diskon:</p>
                            {{-- Tampilkan badge persentase diskon --}}
                            <span class="bg-green-200 text-green-800 text-[10px] font-bold px-1.5 py-0.5 rounded">
                                -{{ is_float($percentage) ? round($percentage, 1) : $percentage }}%
                            </span>
                        </div>
                        <p class="text-sm text-green-600 line-through">Rp {{ number_format((float)$price, 0, ',', '.') }}</p>
                        <p class="text-xl font-bold text-green-800">Rp {{ number_format($this->finalPrice, 0, ',', '.') }}</p>
                    </div>
                @endif
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
            @error('description') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
        </div> 

        {{-- Tombol Submit & Batal --}}
        <div class="flex items-center gap-3">
            <button 
                type="submit" 
                class="cursor-pointer text-black bg-brand box-border border border-transparent hover:bg-brand-strong focus:ring-4 focus:ring-brand-medium shadow-xs font-medium leading-5 rounded-md text-sm px-4 py-2.5 focus:outline-none disabled:opacity-50"
                wire:loading.attr="disabled"
            >
                <span wire:loading.remove>Simpan Paket</span>
                <span wire:loading>Menyimpan...</span>
            </button>
            <a href="{{ route('admin.packages.index') }}" wire:navigate class="text-body bg-neutral-secondary-medium box-border border border-default-medium hover:bg-neutral-tertiary-medium hover:text-heading focus:ring-4 focus:ring-neutral-tertiary shadow-xs font-medium leading-5 rounded-md text-sm px-4 py-2.5 focus:outline-none">
                Batal
            </a>
        </div>
    </form>
</div>