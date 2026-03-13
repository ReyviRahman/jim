<?php

namespace App\Livewire\Admin; // Sesuaikan namespace jika perlu

use Livewire\Component;
use Livewire\Attributes\Validate;
use Livewire\Attributes\Layout;
use Livewire\WithFileUploads;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;

new #[Layout('layouts::admin')] class extends Component 
{
    use WithFileUploads; 

    #[Validate('required|min:3')]
    public $name = '';

    #[Validate('required|integer|min:10')]
    public $age = '';

    #[Validate('required|in:Laki-laki,Perempuan')]
    public $gender = 'Laki-laki'; 

    #[Validate('required|numeric|unique:users,phone')]
    public $phone = '';

    #[Validate('required|email|unique:users,email')]
    public $email = '';
    
    // Foto dibiarkan wajib sesuai kode aslimu
    #[Validate('required|image|max:10048')]
    public $photo = null;

    public $joined_at;

    public function mount()
    {
        $this->joined_at = date('Y-m-d');
    }
    
    public function store() 
    {
        $this->validate();

        // Logika untuk menyimpan foto
        $photoPath = null;
        if ($this->photo) {
            $photoPath = $this->photo->store('profile-photos', 'public');
        }

        User::create([
            'name' => $this->name,
            'age' => $this->age,
            'gender' => $this->gender,
            'phone' => $this->phone,
            'email' => $this->email,
            // Generate password acak 32 karakter agar Sales tidak bisa login
            'password' => Hash::make(Str::random(32)), 
            'photo' => $photoPath,
            'role' => 'sales', // Hardcode role menjadi sales
            'shift' => null,   // Kosongkan shift karena sales tidak pakai shift
            'is_active' => true,
            'joined_at' => $this->joined_at,
        ]);

        session()->flash('success', 'Sales berhasil dibuat!');

        // Sesuaikan nama route ini dengan route halaman list sales kamu
        return $this->redirectRoute('admin.akun.sales.index', navigate: true);
    }
};
?>

<div>
    <h1 class="text-3xl text-center font-semibold mb-6">Buat Akun Sales</h1>
    
    <form wire:submit.prevent="store">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            
            {{-- FOTO PROFIL --}}
            <div class="mb-4 sm:col-span-2">
                <label for="photo" class="block mb-2.5 text-sm font-medium text-heading">Foto Profil Sales (Wajib)</label>
                
                @if ($photo)
                    <div class="mb-3">
                        <img src="{{ $photo->temporaryUrl() }}" class="w-20 h-20 object-cover rounded-full border border-gray-300">
                    </div>
                @endif
    
                <input class="cursor-pointer bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base focus:ring-brand focus:border-brand block w-full shadow-xs placeholder:text-body" type="file" id="photo" wire:model="photo" accept="image/*">
                
                <div wire:loading wire:target="photo" class="text-sm text-gray-500 mt-1">Mengunggah gambar...</div>
                @error('photo') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
            </div>
    
            {{-- NAMA LENGKAP --}}
            <div class="mb-4">
                <label for="name" class="block mb-2.5 text-sm font-medium text-heading">Nama Lengkap</label>
                <input type="text" id="name" wire:model="name"
                    class="bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base focus:accent focus:border-brand block w-full px-3 py-2.5 shadow-xs placeholder:text-body"
                    placeholder="Masukkan Nama Sales" required />
                @error('name') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
            </div>

            {{-- NO HP --}}
            <div class="mb-4">
                <label for="phone" class="block mb-2.5 text-sm font-medium text-heading">No HP / WhatsApp</label>
                <input type="number" id="phone" wire:model='phone'
                    class="bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base focus:accent focus:border-brand block w-full px-3 py-2.5 shadow-xs placeholder:text-body"
                    placeholder="Contoh: 08123456789" required />
                @error('phone') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
            </div>

            {{-- JENIS KELAMIN --}}
            <div class="mb-4">
                <label for="gender" class="block mb-2.5 text-sm font-medium text-heading">Jenis Kelamin</label>
                <select id="gender" wire:model='gender'
                    class="block w-full px-3 py-2.5 bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base focus:ring-brand focus:border-brand shadow-xs placeholder:text-body">
                    <option value="Laki-laki">Laki-laki</option>
                    <option value="Perempuan">Perempuan</option>
                </select>
                @error('gender') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
            </div>

            {{-- USIA --}}
            <div class="mb-4">
                <label for="age" class="block mb-2.5 text-sm font-medium text-heading">Usia</label>
                <input type="number" id="age" wire:model='age'
                    class="bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base focus:accent focus:border-brand block w-full px-3 py-2.5 shadow-xs placeholder:text-body"
                    placeholder="Masukkan Usia" required />
                @error('age') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
            </div>

            {{-- EMAIL --}}
            <div class="mb-4 sm:col-span-2">
                <label for="email" class="block mb-2.5 text-sm font-medium text-heading">Email</label>
                <input type="email" id="email" wire:model='email'
                    class="bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base focus:accent focus:border-brand block w-full px-3 py-2.5 shadow-xs placeholder:text-body"
                    placeholder="Masukkan Email" required />
                @error('email') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
            </div>

        </div>
        
        <button type="submit"
            wire:loading.attr="disabled"
            class="mt-6 text-heading cursor-pointer bg-brand box-border border border-transparent hover:bg-heading hover:text-white focus:ring-4 focus:accent-medium shadow-xs font-medium leading-5 rounded-base text-sm px-4 py-2.5 focus:outline-none w-full disabled:opacity-50 disabled:cursor-not-allowed">
            
            <svg wire:loading wire:target="store" class="animate-spin h-5 w-5 inline-block mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
    
            <span wire:loading.remove wire:target="store">Daftarkan Sales</span>
            <span wire:loading wire:target="store">Memproses...</span>
        </button>
    </form>
</div>