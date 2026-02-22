<?php

use Livewire\Component;
use Illuminate\Support\Facades\Auth;

new class extends Component {
    
    public $email = '';
    public $password = '';
    
    public bool $showPassword = false;

    public function login()
    {
        // 1. Validasi Input
        $this->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        // 2. Coba Login
        if (Auth::attempt(['email' => $this->email, 'password' => $this->password])) {
            
            $user = Auth::user();

            // 3. CEK STATUS AKTIF AKUN DI SINI
            if (!$user->is_active) {
                // Jika tidak aktif, keluarkan user secara paksa (logout)
                Auth::logout();
                session()->invalidate();
                session()->regenerateToken();
                
                // Berikan pesan error
                $this->addError('email', 'Akun Anda tidak aktif. Silakan hubungi admin Frans Gym.');
                return;
            }

            // 4. Jika akun aktif, regenerasi session untuk keamanan
            session()->regenerate();

            // 5. Cek Role & Redirect
            if ($user->role === 'admin') {
                return $this->redirectRoute('admin.absensi.index', navigate: true);
            } 

            if ($user->role === 'pt') {
                return $this->redirectRoute('pt.absensi', navigate: true);
            } 
            
            // Default ke member dashboard
            return $this->redirectRoute('member.dashboard', navigate: true);
        }

        // Jika Gagal Login (Email atau Password salah sama sekali)
        $this->addError('email', 'Email atau password salah.');
    }
};
?>

<div class="flex items-center justify-center mt-30 bg-neutral-primary-soft">
    <div class="w-full max-w-md bg-white p-6 border border-default rounded-md shadow-xs">
        
        <form wire:submit="login">
            <h5 class="text-xl font-semibold text-heading mb-6">Login</h5>
            @if (session()->has('success'))
                <div class="mb-4 p-3 bg-green-100 border border-green-400 text-green-700 rounded text-sm flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    {{ session('success') }}
                </div>
            @endif
            @error('email')
                <div class="mb-4 p-3 bg-red-100 border border-red-400 text-red-700 rounded text-sm">
                    {{ $message }}
                </div>
            @enderror

            <div class="mb-4">
                <label for="email" class="block mb-2.5 text-sm font-medium text-heading">Email</label>
                <input 
                    wire:model="email" 
                    type="email" 
                    id="email"
                    class="bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-md focus:ring-brand focus:border-brand block w-full px-3 py-2.5 shadow-xs placeholder:text-body"
                    placeholder="Masukkan Email Anda" 
                />
            </div>

            <div class="mb-4">
                <label for="password" class="block mb-2.5 text-sm font-medium text-heading">Password</label>
                
                {{-- 2. Bungkus input password dengan div relative --}}
                <div class="relative">
                    <input 
                        wire:model="password" 
                        {{-- 3. Ubah tipe input secara dinamis --}}
                        type="{{ $showPassword ? 'text' : 'password' }}" 
                        id="password"
                        {{-- Tambahkan pr-10 (padding-right) agar teks tidak tertutup ikon --}}
                        class="bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-md focus:ring-brand focus:border-brand block w-full px-3 py-2.5 pr-10 shadow-xs placeholder:text-body"
                        placeholder="Masukkan Password" 
                    />
                    
                    {{-- 4. Tombol Ikon Mata --}}
                    <button 
                        type="button" 
                        wire:click="$toggle('showPassword')" 
                        class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-500 hover:text-gray-700 focus:outline-none"
                    >
                        @if($showPassword)
                            {{-- Ikon Mata Terbuka (Heroicons) --}}
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                            </svg>
                        @else
                            {{-- Ikon Mata Tercoret (Heroicons) --}}
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"></path>
                            </svg>
                        @endif
                    </button>
                </div>
                @error('password') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
            </div>

            <div class="flex items-start my-6">
                <a href="#" class="ms-auto text-sm font-medium text-fg-brand hover:underline">Lupa Password?</a>
            </div>

            <button 
                type="submit"
                wire:loading.attr="disabled"
                class="text-black bg-brand box-border border border-transparent hover:bg-brand-strong focus:ring-4 focus:ring-brand-medium shadow-xs font-medium leading-5 rounded-md text-sm px-4 py-2.5 focus:outline-none w-full mb-3 flex justify-center cursor-pointer disabled:opacity-50"
            >
                <span wire:loading.remove>Login</span>
                <span wire:loading>Memproses...</span>
            </button>

            <div class="text-sm font-medium text-body text-center">
                Belum Daftar? 
                <a href="{{ route('member.register') }}" wire:navigate class="text-fg-brand hover:underline">
                    Buat akun
                </a>
            </div>
        </form>
    </div>
</div>