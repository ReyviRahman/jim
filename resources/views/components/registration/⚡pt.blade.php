<?php

use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\Validate;
use App\Models\User; // Sesuaikan dengan model yang kamu gunakan
use Illuminate\Support\Facades\Hash;

new class extends Component
{
    use WithFileUploads;

    // Tambahkan properti untuk foto profil
    #[Validate('required|image|max:10048')]
    public $photo;

    #[Validate('required|string|min:3')]
    public $name = '';

    #[Validate('required|in:Laki-laki,Perempuan')]
    public $gender = 'Laki-laki';

    #[Validate('required|integer|min:10')]
    public $age = '';

    #[Validate('required|numeric')]
    public $phone = '';

    #[Validate('required|date')]
    public $joined_at;

    #[Validate('required|string')]
    public $alamat = '';

    #[Validate('required|email|unique:users,email')] // Pastikan nama tabel benar
    public $email = '';

    #[Validate('required|min:6')]
    public $password = '';

    public function mount()
    {
        // Set nilai default tanggal hari ini
        $this->joined_at = date('Y-m-d');
    }

    public function store()
    {
        // 1. Jalankan validasi
        $this->validate();

        // 2. Logika untuk menyimpan foto profil
        $photoPath = null;
        if ($this->photo) {
            $photoPath = $this->photo->store('profile-photos', 'public');
        }

        // 3. Simpan data ke database
        User::create([
            'name'      => $this->name,
            'gender'    => $this->gender,
            'age'       => $this->age,
            'phone'     => $this->phone,
            'joined_at' => $this->joined_at,
            'alamat'    => $this->alamat,
            'email'     => $this->email,
            'password'  => Hash::make($this->password),
            'photo'     => $photoPath, 
            'is_active'     => false, 
            'occupation'     => 'Personal Trainer', 
            'role'     => 'pt', 
        ]);

        // 4. Beri notifikasi dan redirect
        session()->flash('success', 'Personal Trainer berhasil didaftarkan! Hubungi Admin untuk Aktifkan Akun.');
        return $this->redirectRoute('login');
    }
};
?>

<div>
    @if (session()->has('success'))
        <div class="mb-4 p-4 text-sm text-green-800 rounded-lg bg-green-50">
            {{ session('success') }}
        </div>
    @endif

    <form wire:submit="store">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            
            <div class="mb-4 sm:col-span-2">
                <label for="photo" class="block mb-2.5 text-sm font-medium text-heading">Foto Profil (Wajib)</label>
                
                @if ($photo)
                    <div class="mb-3">
                        <img src="{{ $photo->temporaryUrl() }}" class="w-20 h-20 object-cover rounded-full border border-gray-300">
                    </div>
                @endif

                <input class="cursor-pointer bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base focus:ring-brand focus:border-brand block w-full shadow-xs placeholder:text-body" type="file" id="photo" wire:model="photo" accept="image/*">
                
                <div wire:loading wire:target="photo" class="text-sm text-gray-500 mt-1">Mengunggah gambar...</div>
                
                @error('photo') <span class="text-red-500 text-sm block mt-1">{{ $message }}</span> @enderror
            </div>

            <div class="mb-4">
                <label for="name" class="block mb-2.5 text-sm font-medium text-heading">Nama</label>
                <input type="text" id="name" wire:model="name"
                    class="bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base focus:accent focus:border-brand block w-full px-3 py-2.5 shadow-xs placeholder:text-body"
                    placeholder="Masukkan Nama" required />
                @error('name') <span class="text-red-500 text-sm block mt-1">{{ $message }}</span> @enderror
            </div>
            
            <div class="mb-4">
                <label for="gender" class="block mb-2.5 text-sm font-medium text-heading">Jenis Kelamin</label>
                <select id="gender" name="gender" wire:model="gender"
                    class="block w-full px-3 py-2.5 bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base focus:ring-brand focus:border-brand shadow-xs placeholder:text-body">
                    <option value="Laki-laki">Laki-laki</option>
                    <option value="Perempuan">Perempuan</option>
                </select>
                @error('gender') <span class="text-red-500 text-sm block mt-1">{{ $message }}</span> @enderror
            </div>
            
            <div class="mb-4">
                <label for="age" class="block mb-2.5 text-sm font-medium text-heading">Usia</label>
                <input type="number" id="age" wire:model="age"
                    class="bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base focus:accent focus:border-brand block w-full px-3 py-2.5 shadow-xs placeholder:text-body"
                    placeholder="Masukkan Usia" required />
                @error('age') <span class="text-red-500 text-sm block mt-1">{{ $message }}</span> @enderror
            </div>
            
            <div class="mb-4">
                <label for="phone" class="block mb-2.5 text-sm font-medium text-heading">No HP / WhatsApp</label>
                <input type="number" id="phone" wire:model="phone"
                    class="bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base focus:accent focus:border-brand block w-full px-3 py-2.5 shadow-xs placeholder:text-body"
                    placeholder="Masukkan No HP" required />
                @error('phone') <span class="text-red-500 text-sm block mt-1">{{ $message }}</span> @enderror
            </div>
            
            <div class="mb-4">
                <label for="joined_at" class="block mb-2.5 text-sm font-medium text-heading">Tanggal Join Frans Gym</label>
                <input type="date" id="joined_at" wire:model="joined_at" 
                    class="bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base focus:accent focus:border-brand block w-full px-3 py-2.5 shadow-xs placeholder:text-body"
                    required />
                @error('joined_at') <span class="text-red-500 text-sm block mt-1">{{ $message }}</span> @enderror
            </div>
            
            <div class="mb-4">
                <label for="alamat" class="block mb-2.5 text-sm font-medium text-heading">Alamat</label>
                <input type="text" id="alamat" wire:model="alamat"
                    class="bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base focus:accent focus:border-brand block w-full px-3 py-2.5 shadow-xs placeholder:text-body"
                    placeholder="Masukkan Alamat" required />
                @error('alamat') <span class="text-red-500 text-sm block mt-1">{{ $message }}</span> @enderror
            </div>
            
            <div class="mb-4">
                <label for="email" class="block mb-2.5 text-sm font-medium text-heading">Email</label>
                <input type="email" id="email" wire:model="email"
                    class="bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base focus:accent focus:border-brand block w-full px-3 py-2.5 shadow-xs placeholder:text-body"
                    placeholder="Masukkan Email" required />
                @error('email') <span class="text-red-500 text-sm block mt-1">{{ $message }}</span> @enderror
            </div>
            
            <div>
                <label for="password" class="block mb-2.5 text-sm font-medium text-heading">Password</label>
                <input type="password" id="password" wire:model="password"
                    class="bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base focus:accent focus:border-brand block w-full px-3 py-2.5 shadow-xs placeholder:text-body"
                    placeholder="•••••••••" required />
                @error('password') <span class="text-red-500 text-sm block mt-1">{{ $message }}</span> @enderror
            </div>
        </div>
        
        <button type="submit" wire:loading.attr="disabled"
            class="mt-4 text-heading cursor-pointer bg-brand box-border border border-transparent hover:bg-heading hover:text-white focus:ring-4 focus:accent-medium shadow-xs font-medium leading-5 rounded-base text-sm px-4 py-2.5 focus:outline-none w-full mb-3 disabled:opacity-50 disabled:cursor-not-allowed">
            
            <svg wire:loading wire:target="store" class="animate-spin h-5 w-5 inline-block mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>

            <span wire:loading.remove wire:target="store">Daftarkan Akun</span>
            <span wire:loading wire:target="store">Memproses...</span>
        </button>
    </form>
</div>