<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use App\Models\User;

new #[Layout('layouts::admin')] class extends Component
{
    public User $user;

    // Livewire otomatis mencari User berdasarkan ID di URL
    public function mount(User $user)
    {
        $this->user = $user;
    }
};
?>

<div>
    <div class="mb-6">
        <a href="{{ route('admin.akun.index') }}" wire:navigate class="inline-flex items-center text-sm font-medium text-body hover:text-heading transition-colors">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
            </svg>
            Kembali ke Daftar Akun
        </a>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        
        <div class="col-span-1">
            <div class="bg-neutral-primary-soft border border-default rounded-base shadow-xs p-6 text-center">
                @if($user->photo)
                    <img class="w-32 h-32 rounded-full object-cover mx-auto mb-4 border-4 border-neutral-secondary-medium" src="{{ asset('storage/' . $user->photo) }}" alt="{{ $user->name }}">
                @else
                    <img class="w-32 h-32 rounded-full object-cover mx-auto mb-4 border-4 border-neutral-secondary-medium" src="https://ui-avatars.com/api/?name={{ urlencode($user->name) }}&background=random&size=128" alt="{{ $user->name }}">
                @endif
                
                <h2 class="text-xl font-bold text-heading">{{ $user->name }}</h2>
                <p class="text-sm text-body mb-4">{{ $user->email }}</p>

                <span class="inline-flex items-center px-3 py-1 text-sm font-medium rounded-full {{ $user->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                    <span class="w-2 h-2 mr-2 rounded-full {{ $user->is_active ? 'bg-green-500' : 'bg-red-500' }}"></span>
                    {{ $user->is_active ? 'Akun Aktif' : 'Akun Tidak Aktif' }}
                </span>
            </div>
        </div>

        <div class="col-span-1 md:col-span-2">
            <div class="bg-neutral-primary-soft border border-default rounded-base shadow-xs p-6">
                <h3 class="text-lg font-semibold text-heading mb-4 border-b border-default-medium pb-2">Informasi Personal</h3>
                
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-y-4 gap-x-6">
                    <div>
                        <label class="block text-sm font-medium text-body mb-1">Usia & Jenis Kelamin</label>
                        <p class="text-base text-heading">{{ $user->age }} Tahun / {{ $user->gender }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-body mb-1">Pekerjaan</label>
                        <p class="text-base text-heading">{{ $user->occupation ?? '-' }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-body mb-1">No HP / WhatsApp</label>
                        <p class="text-base text-heading">{{ $user->phone }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-body mb-1">Tanggal Bergabung</label>
                        <p class="text-base text-heading">{{ $user->joined_at ? $user->joined_at->format('d F Y') : '-' }}</p>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-body mb-1">Alamat</label>
                        <p class="text-base text-heading">{{ $user->address ?? '-' }}</p>
                    </div>
                </div>

                <h3 class="text-lg font-semibold text-heading mb-4 border-b border-default-medium pb-2 mt-8">Informasi Medis & Keanggotaan</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-y-4 gap-x-6">
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-body mb-1">Riwayat Penyakit</label>
                        @if($user->medical_history)
                            <div class="p-3 bg-red-50 border border-red-200 text-red-800 rounded-md text-sm">
                                ⚠️ {{ $user->medical_history }}
                            </div>
                        @else
                            <p class="text-base text-heading">Tidak ada riwayat penyakit.</p>
                        @endif
                    </div>
                    
                    <div class="sm:col-span-2 mt-2">
                        <label class="block text-sm font-medium text-body mb-1">Status Membership Gym</label>
                        @if($user->activeMembership())
                            <span class="inline-flex items-center px-3 py-1 text-sm font-medium rounded-md bg-brand-softer text-fg-brand border border-brand-soft">
                                Member Aktif
                            </span>
                        @else
                            <span class="inline-flex items-center px-3 py-1 text-sm font-medium rounded-md bg-neutral-secondary-medium text-body border border-default-medium">
                                Tidak ada paket aktif
                            </span>
                        @endif
                    </div>
                </div>

            </div>
        </div>

    </div>
</div>