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

    // --- PERUBAHAN: 'visit' masuk ke type ---
    #[Validate('required|in:gym,pt,visit')]
    public $type = 'gym';

    #[Validate('nullable|integer|min:1')]
    public $pt_sessions = '';

    // --- PERUBAHAN: 'visit' dihapus dari category ---
    #[Validate('required|in:single,couple,group')]
    public $category = 'single';

    #[Validate('required|integer|min:1')]
    public $max_members = 1;

    #[Validate('required|numeric|min:0')]
    public $price = '';

    #[Validate('nullable|numeric|min:0')]
    public $discount = ''; 

    // Reset dan atur UI jika Tipe diubah
    public function updatedType($value)
    {
        // Jika bukan PT, kosongkan sesi PT
        if (in_array($value, ['gym', 'visit'])) {
            $this->pt_sessions = '';
        }

        // Jika Visit, paksa kategori ke single dan max member 1
        if ($value === 'visit') {
            $this->category = 'single';
            $this->max_members = 1;
        }
    }

    // Otomatis mengubah max_members saat kategori diubah
    public function updatedCategory($value)
    {
        // Cegah perubahan kategori jika tipenya visit
        if ($this->type === 'visit') {
            $this->category = 'single';
            $this->max_members = 1;
            return;
        }

        if ($value === 'single') {
            $this->max_members = 1;
        } elseif ($value === 'couple') {
            $this->max_members = 2;
        } elseif ($value === 'group') {
            if ($this->max_members < 3) {
                $this->max_members = 3; 
            }
        }
    }

    #[Computed]
    public function finalPrice()
    {
        $basePrice = (float) ($this->price ?: 0);
        $discountAmount = (float) ($this->discount ?: 0);
        
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
        // Validasi kustom untuk PT
        if ($this->type === 'pt' && empty($this->pt_sessions)) {
            $this->addError('pt_sessions', 'Jumlah sesi wajib diisi untuk paket Personal Trainer.');
            return;
        }

        if ((float) $this->discount > (float) $this->price) {
            $this->addError('discount', 'Diskon tidak boleh lebih besar dari harga paket.');
            return;
        }

        $this->validate();

        GymPackage::create([
            'name' => $this->name,
            'type' => $this->type,                         
            'pt_sessions' => $this->type === 'pt' ? $this->pt_sessions : null, 
            'category' => $this->category, 
            'max_members' => $this->max_members, 
            'price' => $this->price,
            'discount' => $this->discount === '' ? 0 : $this->discount,
            // 'description' => $this->description, // Tambahkan ini jika Anda punya properti $description di class ini
        ]);

        $this->reset(); 
        session()->flash('success', 'Paket berhasil dibuat!');
        
        return redirect()->to(route('admin.packages.index'));
    }
};
?>

<div>
    @if (session()->has('success'))
        <div class="p-4 mb-4 text-sm text-green-800 rounded-lg bg-green-50" role="alert">
            <span class="font-medium">Sukses!</span> {{ session('success') }}
        </div>
    @endif

    <form wire:submit="save">
        <h5 class="text-xl font-semibold text-heading mb-6">Buat Paket Membership / PT</h5>
        
        <div class="grid gap-6 mb-6 md:grid-cols-2">
            
            {{-- 1. Nama Paket --}}
            <div class="md:col-span-2">
                <label for="name" class="block mb-2.5 text-sm font-medium text-heading">Nama Paket</label>
                <input 
                    type="text" 
                    id="name" 
                    wire:model="name"
                    class="bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-md focus:ring-brand focus:border-brand block w-full px-3 py-2.5 shadow-xs placeholder:text-body" 
                    placeholder="Contoh: Daily Pass" 
                    required 
                />
                @error('name') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
            </div>

            {{-- 2. TIPE PAKET (BARU) --}}
            <div>
                <label for="type" class="block mb-2.5 text-sm font-medium text-heading">Tipe Layanan</label>
                <select 
                    id="type" 
                    wire:model.live="type"
                    class="bg-white border border-brand-medium text-brand-strong font-semibold text-sm rounded-md focus:ring-brand focus:border-brand block w-full px-3 py-2.5 shadow-xs"
                >
                    <option value="gym">🏋️ Gym Membership</option>
                    <option value="pt">👨‍🏫 Personal Trainer (PT)</option>
                    <option value="visit">🎟️ Visit / Harian</option> {{-- TAMBAHAN VISIT --}}
                </select>
                @error('type') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
            </div>

            {{-- 3. JUMLAH SESI PT --}}
            @if($type === 'pt')
                <div class="animate-pulse-once"> 
                    <label for="pt_sessions" class="block mb-2.5 text-sm font-medium text-heading">Jumlah Sesi</label>
                    <input type="number" id="pt_sessions" wire:model="pt_sessions" min="1" class="bg-white border border-blue-400 text-blue-800 text-sm rounded-md focus:ring-blue-500 focus:border-blue-500 block w-full px-3 py-2.5 shadow-xs placeholder:text-blue-300" placeholder="Contoh: 12" required />
                    @error('pt_sessions') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
            @else
                <div class="hidden md:block"></div> 
            @endif

            <div class="md:col-span-2 border-t border-default-medium mt-2 pt-4"></div>

            {{-- 4. Kategori Paket --}}
            <div>
                <label for="category" class="block mb-2.5 text-sm font-medium text-heading">Kategori / Kapasitas Orang</label>
                <select 
                    id="category" 
                    wire:model.live="category"
                    class="bg-white border border-default-medium text-heading text-sm rounded-md focus:ring-brand focus:border-brand block w-full px-3 py-2.5 shadow-xs disabled:opacity-50 disabled:bg-gray-100"
                    @if($type === 'visit') disabled @endif {{-- Disable jika visit --}}
                >
                    <option value="single">Single (1 Orang)</option>
                    <option value="couple">Couple (2 Orang)</option>
                    <option value="group">Group (3+ Orang)</option>
                </select>
                @if($type === 'visit')
                    <p class="mt-1 text-xs text-gray-500">Terkunci ke Single karena ini adalah paket Visit.</p>
                @endif
                @error('category') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
            </div>

            {{-- 5. Maksimal Member --}}
            <div>
                <label for="max_members" class="block mb-2.5 text-sm font-medium text-heading">Maksimal Member</label>
                <input 
                    type="number" 
                    id="max_members" 
                    wire:model="max_members"
                    min="1"
                    class="bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-md focus:ring-brand focus:border-brand block w-full px-3 py-2.5 shadow-xs disabled:opacity-50 disabled:bg-gray-100" 
                    @if(in_array($category, ['single', 'couple']) || $type === 'visit') disabled @endif
                    required 
                />
                @if(in_array($category, ['single', 'couple']) || $type === 'visit')
                    <p class="mt-1 text-xs text-gray-500">Angka terkunci otomatis berdasarkan kategori/tipe.</p>
                @else
                    <p class="mt-1 text-xs text-brand-strong">Silakan tentukan kapasitas maksimal grup.</p>
                @endif
                @error('max_members') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
            </div>

            {{-- 6. Harga --}}
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

            {{-- 7. Diskon Nominal --}}
            <div>
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
            
            {{-- 8. Preview Harga Akhir --}}
            <div class="md:col-span-2 flex items-end">
                @if($price > 0 && $discount > 0)
                    @php
                        $percentage = ($discount / $price) * 100;
                    @endphp
                    <div class="w-full md:w-1/2 p-4 bg-green-50 border border-green-200 rounded-md">
                        <div class="flex justify-between items-center mb-1">
                            <p class="text-xs text-green-700 font-medium">Harga Setelah Diskon:</p>
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