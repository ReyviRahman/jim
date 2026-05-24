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

            if ($user->role === 'sales') {
                Auth::logout();
                session()->invalidate();
                session()->regenerateToken();
                return;
            }

            // 4. Jika akun aktif, regenerasi session untuk keamanan
            session()->regenerate();

            // 5. Cek Role & Redirect
            if ($user->role === 'admin') {
                return $this->redirectRoute('admin.absensi.index', navigate: true);
            }

            if ($user->role === 'head_coach') {
                return $this->redirectRoute('admin.cicilan.index', navigate: true);
            } 

            if ($user->role === 'kasir_gym') {
                return $this->redirectRoute('admin.absensi.index', navigate: true);
            } 

            if ($user->role === 'pt') {
                return $this->redirectRoute('pt.absensi', navigate: true);
            } 

            if ($user->role === 'kasir_minum') {
                return $this->redirectRoute('admin.beverages.index', navigate: true);
            } 
            
            // Default ke member dashboard
            return $this->redirectRoute('member.dashboard', navigate: true);
        }

        // Jika Gagal Login (Email atau Password salah sama sekali)
        $this->addError('email', 'Email atau password salah.');
    }
};
?>

<div class="min-h-screen bg-gradient-to-b from-black via-zinc-900 to-black flex items-center justify-center px-4 pt-20 pb-8">

    {{-- Background Glow --}}
    <div class="absolute top-0 left-0 w-72 h-72 bg-brand/20 blur-3xl rounded-full"></div>
    <div class="absolute bottom-0 right-0 w-72 h-72 bg-secondary/20 blur-3xl rounded-full"></div>

    <div class="relative w-full max-w-md">

        {{-- Card --}}
        <div class="backdrop-blur-xl bg-white/5 border border-white/10 rounded-[32px] shadow-2xl p-6 sm:p-8">

            <form wire:submit="login">

                {{-- Logo --}}
                <div class="flex flex-col items-center mb-8">

                    <div class="w-24 h-24 rounded-full bg-white/10 border border-white/10 flex items-center justify-center shadow-lg mb-4">
                        <img 
                            src="{{ asset('icon.png') }}" 
                            alt="Logo FRANS GYM" 
                            class="w-16 h-16 object-contain"
                        >
                    </div>

                    <h1 class="text-white text-3xl font-black tracking-wide">
                        FRANS GYM
                    </h1>

                </div>

                {{-- Success --}}
                @if (session()->has('success'))
                    <div class="mb-5 p-4 bg-green-500/10 border border-green-500/20 text-green-300 rounded-2xl text-sm flex items-center">
                        <svg class="w-5 h-5 mr-2 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>

                        {{ session('success') }}
                    </div>
                @endif

                {{-- Error --}}
                @error('email')
                    <div class="mb-5 p-4 bg-red-500/10 border border-red-500/20 text-red-300 rounded-2xl text-sm">
                        {{ $message }}
                    </div>
                @enderror

                {{-- Email --}}
                <div class="mb-4">

                    <label class="text-zinc-300 text-sm mb-2 block">
                        Email
                    </label>

                    <input 
                        wire:model="email" 
                        type="email" 
                        id="email"
                        placeholder="Masukkan email"
                        class="w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-4 text-white placeholder:text-zinc-500 focus:outline-none focus:ring-2 focus:ring-brand/50 focus:border-brand transition-all"
                    />

                </div>

                {{-- Password --}}
                <div class="mb-2">

                    <label class="text-zinc-300 text-sm mb-2 block">
                        Password
                    </label>

                    <div class="relative">

                        <input 
                            wire:model="password" 
                            type="{{ $showPassword ? 'text' : 'password' }}"
                            id="password"
                            placeholder="Masukkan password"
                            class="w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-4 pr-12 text-white placeholder:text-zinc-500 focus:outline-none focus:ring-2 focus:ring-brand/50 focus:border-brand transition-all"
                        />

                        <button 
                            type="button"
                            wire:click="$toggle('showPassword')"
                            class="absolute right-4 top-1/2 -translate-y-1/2 text-zinc-400 hover:text-white transition"
                        >

                            @if($showPassword)

                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                </svg>

                            @else

                                <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" width="128" height="128" viewBox="0 0 24 24"><path fill="currentColor" d="M2.54 4.71L3.25 4L20 20.75l-.71.71l-3.34-3.35c-1.37.57-2.87.89-4.45.89c-4.56 0-8.5-2.65-10.36-6.5c.97-2 2.49-3.67 4.36-4.82zM11.5 18c1.29 0 2.53-.23 3.67-.66l-1.12-1.13c-.73.5-1.6.79-2.55.79C9 17 7 15 7 12.5c0-.95.29-1.82.79-2.55L6.24 8.41a10.64 10.64 0 0 0-3.98 4.09C4.04 15.78 7.5 18 11.5 18m9.24-5.5C18.96 9.22 15.5 7 11.5 7c-1.15 0-2.27.19-3.31.53l-.78-.78C8.68 6.26 10.06 6 11.5 6c4.56 0 8.5 2.65 10.36 6.5a11.47 11.47 0 0 1-4.07 4.63l-.72-.73c1.53-.96 2.8-2.3 3.67-3.9M11.5 8C14 8 16 10 16 12.5c0 .82-.22 1.58-.6 2.24l-.74-.74c.22-.46.34-.96.34-1.5A3.5 3.5 0 0 0 11.5 9c-.54 0-1.04.12-1.5.34l-.74-.74c.66-.38 1.42-.6 2.24-.6M8 12.5a3.5 3.5 0 0 0 3.5 3.5c.67 0 1.29-.19 1.82-.5L8.5 10.68c-.31.53-.5 1.15-.5 1.82"/></svg>

                            @endif

                        </button>

                    </div>

                    @error('password')
                        <span class="text-red-400 text-xs mt-2 block">
                            {{ $message }}
                        </span>
                    @enderror

                </div>

                {{-- Forgot Password --}}
                <div class="flex justify-end mt-3 mb-8">
                    <a 
                        href="#" 
                        class="text-sm text-brand hover:text-brand-light transition"
                    >
                        Lupa Password?
                    </a>
                </div>

                {{-- Button --}}
                <button 
                    type="submit"
                    wire:loading.attr="disabled"
                    class="w-full rounded-2xl bg-brand text-white font-bold py-4 shadow-lg shadow-brand/30 hover:scale-[1.02] active:scale-[0.98] transition-all duration-200 disabled:opacity-50"
                >
                    <span wire:loading.remove>
                        Login
                    </span>

                    <span wire:loading>
                        Memproses...
                    </span>
                </button>

                {{-- Register --}}
                <div class="text-center mt-8 text-sm text-zinc-400">

                    Belum punya akun?

                    <a 
                        href="{{ route('member.register') }}"
                        wire:navigate
                        class="text-brand font-semibold hover:underline ml-1"
                    >
                        Daftar
                    </a>

                </div>

            </form>

        </div>

    </div>

</div>