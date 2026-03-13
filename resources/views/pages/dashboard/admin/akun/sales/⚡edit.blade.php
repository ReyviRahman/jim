<?php

namespace App\Livewire\Admin\Sales; // Sesuaikan namespace dengan folder Anda

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\WithFileUploads;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

new #[Layout('layouts::admin')] class extends Component 
{
    use WithFileUploads; 

    public $userId;
    public $name = '';
    public $age = '';
    public $gender = 'Laki-laki'; 
    public $phone = '';
    public $email = '';
    
    // Untuk menampung file upload baru
    public $photo = null;
    
    // Untuk menampilkan foto lama dari database
    public $currentPhoto = null; 

    // Tidak bisa menggunakan atribut #[Validate] untuk rule dinamis (seperti ignore ID),
    // Jadi kita gunakan fungsi rules() bawaan Livewire
    protected function rules()
    {
        return [
            'name' => 'required|min:3',
            'age' => 'required|integer|min:10',
            'gender' => 'required|in:Laki-laki,Perempuan',
            // Ignore unique rule untuk ID milik user ini sendiri
            'phone' => 'required|numeric|unique:users,phone,' . $this->userId,
            'email' => 'required|email|unique:users,email,' . $this->userId,
            // Foto tidak lagi required saat edit, tapi jika diisi wajib berupa gambar
            'photo' => 'nullable|image|max:10048', 
        ];
    }

    public function mount(User $user) // Menangkap parameter ID dari route
    {
        // Pastikan yang diedit benar-benar role sales (Keamanan ekstra)
        if ($user->role !== 'sales') {
            abort(403, 'Akses ditolak. User ini bukan Sales.');
        }

        $this->userId = $user->id;
        $this->name = $user->name;
        $this->age = $user->age;
        $this->gender = $user->gender;
        $this->phone = $user->phone;
        $this->email = $user->email;
        $this->currentPhoto = $user->photo;
    }
    
    public function update() 
    {
        $this->validate();

        $user = User::findOrFail($this->userId);
        
        // Simpan path foto saat ini (default)
        $photoPath = $user->photo;

        // Jika admin mengunggah foto baru
        if ($this->photo) {
            // Hapus foto lama dari storage jika ada
            if ($user->photo) {
                Storage::disk('public')->delete($user->photo);
            }
            // Simpan foto baru
            $photoPath = $this->photo->store('profile-photos', 'public');
        }

        $user->update([
            'name' => $this->name,
            'age' => $this->age,
            'gender' => $this->gender,
            'phone' => $this->phone,
            'email' => $this->email,
            'photo' => $photoPath,
            // password, role, dan shift tidak perlu di-update
        ]);

        session()->flash('success', 'Data Sales berhasil diperbarui!');

        // Sesuaikan dengan nama route index sales Anda
        return $this->redirectRoute('admin.akun.sales.index', navigate: true);
    }
};

?>

<div>
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-3xl font-semibold">Edit Data Sales</h1>
        {{-- Tombol Kembali --}}
        <a href="{{ route('admin.akun.sales.index') }}" wire:navigate class="px-4 py-2 bg-gray-100 text-gray-700 rounded-md border border-gray-300 hover:bg-gray-200 text-sm font-medium">
            &larr; Kembali
        </a>
    </div>
    
    <form wire:submit.prevent="update" class="bg-white p-6 shadow-sm rounded-lg border border-gray-100">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
            
            {{-- FOTO PROFIL --}}
            <div class="sm:col-span-2">
                <label for="photo" class="block mb-2.5 text-sm font-medium text-heading">Foto Profil Sales</label>
                
                <div class="flex items-center gap-4 mb-3">
                    {{-- Tampilkan foto (Preview file baru ATAU foto lama dari DB) --}}
                    @if ($photo)
                        <img src="{{ $photo->temporaryUrl() }}" class="w-20 h-20 object-cover rounded-full border border-brand shadow-sm">
                    @elseif ($currentPhoto)
                        <img src="{{ asset('storage/' . $currentPhoto) }}" class="w-20 h-20 object-cover rounded-full border border-gray-300 shadow-sm">
                    @else
                        <div class="w-20 h-20 bg-gray-100 rounded-full border border-gray-300 flex items-center justify-center text-gray-400">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                        </div>
                    @endif
                    
                    <div class="flex-1">
                        <input class="cursor-pointer bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base focus:ring-brand focus:border-brand block w-full shadow-xs placeholder:text-body" type="file" id="photo" wire:model="photo" accept="image/*">
                        <p class="text-xs text-gray-500 mt-1.5">*Kosongkan jika tidak ingin mengubah foto</p>
                    </div>
                </div>
                
                <div wire:loading wire:target="photo" class="text-sm text-brand font-medium mt-1">Memproses gambar...</div>
                @error('photo') <span class="text-red-500 text-sm block mt-1">{{ $message }}</span> @enderror
            </div>
    
            {{-- NAMA LENGKAP --}}
            <div>
                <label for="name" class="block mb-2.5 text-sm font-medium text-heading">Nama Lengkap</label>
                <input type="text" id="name" wire:model="name"
                    class="bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base focus:accent focus:border-brand block w-full px-3 py-2.5 shadow-xs placeholder:text-body"
                    placeholder="Masukkan Nama Sales" required />
                @error('name') <span class="text-red-500 text-sm mt-1 block">{{ $message }}</span> @enderror
            </div>

            {{-- NO HP --}}
            <div>
                <label for="phone" class="block mb-2.5 text-sm font-medium text-heading">No HP / WhatsApp</label>
                <input type="number" id="phone" wire:model='phone'
                    class="bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base focus:accent focus:border-brand block w-full px-3 py-2.5 shadow-xs placeholder:text-body"
                    placeholder="Contoh: 08123456789" required />
                @error('phone') <span class="text-red-500 text-sm mt-1 block">{{ $message }}</span> @enderror
            </div>

            {{-- JENIS KELAMIN --}}
            <div>
                <label for="gender" class="block mb-2.5 text-sm font-medium text-heading">Jenis Kelamin</label>
                <select id="gender" wire:model='gender'
                    class="block w-full px-3 py-2.5 bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base focus:ring-brand focus:border-brand shadow-xs placeholder:text-body">
                    <option value="Laki-laki">Laki-laki</option>
                    <option value="Perempuan">Perempuan</option>
                </select>
                @error('gender') <span class="text-red-500 text-sm mt-1 block">{{ $message }}</span> @enderror
            </div>

            {{-- USIA --}}
            <div>
                <label for="age" class="block mb-2.5 text-sm font-medium text-heading">Usia</label>
                <input type="number" id="age" wire:model='age'
                    class="bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base focus:accent focus:border-brand block w-full px-3 py-2.5 shadow-xs placeholder:text-body"
                    placeholder="Masukkan Usia" required />
                @error('age') <span class="text-red-500 text-sm mt-1 block">{{ $message }}</span> @enderror
            </div>

            {{-- EMAIL --}}
            <div class="sm:col-span-2">
                <label for="email" class="block mb-2.5 text-sm font-medium text-heading">Email</label>
                <input type="email" id="email" wire:model='email'
                    class="bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base focus:accent focus:border-brand block w-full px-3 py-2.5 shadow-xs placeholder:text-body"
                    placeholder="Masukkan Email" required />
                @error('email') <span class="text-red-500 text-sm mt-1 block">{{ $message }}</span> @enderror
            </div>

        </div>
        
        <hr class="my-6 border-gray-200">

        <button type="submit"
            wire:loading.attr="disabled"
            class="text-heading cursor-pointer bg-brand box-border border border-transparent hover:bg-heading hover:text-white focus:ring-4 focus:accent-medium shadow-xs font-medium leading-5 rounded-base text-sm px-6 py-2.5 focus:outline-none disabled:opacity-50 disabled:cursor-not-allowed">
            
            <svg wire:loading wire:target="update" class="animate-spin h-5 w-5 inline-block mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
    
            <span wire:loading.remove wire:target="update">Simpan Perubahan</span>
            <span wire:loading wire:target="update">Menyimpan...</span>
        </button>
    </form>
</div>