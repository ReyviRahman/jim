<?php

use Livewire\Component;
use Illuminate\Support\Facades\Auth;

new class extends Component {
    
    public $email = '';
    public $password = '';

    public function login()
    {
        // 1. Validasi Input
        $this->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        // 2. Coba Login
        if (Auth::attempt(['email' => $this->email, 'password' => $this->password])) {
            
            // Regenerate session untuk keamanan (mencegah session fixation)
            session()->regenerate();

            $user = Auth::user();

            // 3. Cek Role & Redirect
            if ($user->role === 'admin') {
                return $this->redirectRoute('admin.packages.index', navigate: true);
            } 
            
            // Default ke member dashboard
            return $this->redirectRoute('member.dashboard', navigate: true);
        }

        // 4. Jika Gagal Login
        $this->addError('email', 'Email atau password salah.');
    }
};
?>

<div class="flex items-center justify-center mt-30 bg-neutral-primary-soft">
    <div class="w-full max-w-md bg-white p-6 border border-default rounded-base shadow-xs">
        
        <form wire:submit="login">
            <h5 class="text-xl font-semibold text-heading mb-6">Login</h5>
            
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
                    class="bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base focus:ring-brand focus:border-brand block w-full px-3 py-2.5 shadow-xs placeholder:text-body"
                    placeholder="Masukkan Email Anda" 
                />
                @error('email') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
            </div>

            <div class="mb-4">
                <label for="password" class="block mb-2.5 text-sm font-medium text-heading">Password</label>
                <input 
                    wire:model="password" 
                    type="password" 
                    id="password"
                    class="bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base focus:ring-brand focus:border-brand block w-full px-3 py-2.5 shadow-xs placeholder:text-body"
                    placeholder="Masukkan Password" 
                />
                @error('password') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
            </div>

            <div class="flex items-start my-6">
                <a href="#" class="ms-auto text-sm font-medium text-fg-brand hover:underline">Lupa Password?</a>
            </div>

            <button 
                type="submit"
                wire:loading.attr="disabled"
                class="text-black bg-brand box-border border border-transparent hover:bg-brand-strong focus:ring-4 focus:ring-brand-medium shadow-xs font-medium leading-5 rounded-base text-sm px-4 py-2.5 focus:outline-none w-full mb-3 flex justify-center"
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