<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use App\Models\GymPackage;

new #[Layout('layouts::member')] class extends Component
{
    // Mengambil data paket yang statusnya Aktif
    #[Computed]
    public function packages()
    {
        return GymPackage::where('is_active', true)->get();
    }
    
    public function selectPackage($id)
    {
        // Cek jika user sudah punya membership aktif
        if (Auth::user()->activeMembership()) {
            session()->flash('error', 'Anda masih memiliki membership aktif.');
            return;
        }

        // Redirect ke halaman checkout membawa ID paket
        return $this->redirectRoute('member.paket.checkout', ['package' => $id], navigate: true);
    }
};
?>

<div class="py-10 px-4 sm:px-6 lg:px-8">
    
    <div class="text-center mb-12">
        <h2 class="text-3xl font-extrabold text-heading sm:text-4xl">
            Pilih Paket Membership
        </h2>
        <p class="mt-4 text-lg text-body">
            Investasikan kesehatan Anda dengan paket terbaik kami.
        </p>
    </div>
    @if (session()->has('error'))
        <div class="mb-6 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
            <strong class="font-bold">Gagal!</strong>
            <span class="block sm:inline">{{ session('error') }}</span>
        </div>
    @endif
    <div class="grid grid-cols-1 gap-8 sm:grid-cols-2 lg:grid-cols-3">
        
        @forelse ($this->packages as $package)
            <div class="bg-white rounded-2xl shadow-sm border border-default flex flex-col hover:shadow-lg transition-shadow duration-300 relative overflow-hidden">
                
                <div class="absolute top-0 w-full h-2 bg-brand"></div>

                <div class="p-6 flex-1 flex flex-col">
                    <h3 class="text-xl font-semibold text-heading mb-2">
                        {{ $package->name }}
                    </h3>

                    <p class="text-sm text-body mb-6">
                        {{ $package->description ?? 'Paket membership gym profesional.' }}
                    </p>

                    <div class="my-4 flex items-baseline">
                        <span class="text-3xl font-bold text-heading">
                            Rp {{ number_format($package->price, 0, ',', '.') }}
                        </span>
                        {{-- <span class="text-body ml-1 text-sm">/ bulan</span> --}}
                    </div>

                    <ul role="list" class="mt-6 space-y-4 mb-6 flex-1">
                        <li class="flex items-start">
                            <svg class="flex-shrink-0 w-5 h-5 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                            </svg>
                            <span class="ml-3 text-sm text-heading font-medium">
                                {{ $package->number_of_sessions }} Sesi Latihan
                            </span>
                        </li>
                        <li class="flex items-start">
                            <svg class="flex-shrink-0 w-5 h-5 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                            </svg>
                            <span class="ml-3 text-sm text-body">
                                Akses Fasilitas Gym
                            </span>
                        </li>
                         <li class="flex items-start">
                            <svg class="flex-shrink-0 w-5 h-5 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                            </svg>
                            <span class="ml-3 text-sm text-body">
                                Free Konsultasi Trainer
                            </span>
                        </li>
                    </ul>

                    <button 
                        wire:click="selectPackage({{ $package->id }})"
                        class="mt-auto w-full bg-brand hover:bg-brand-strong text-white font-semibold py-2.5 px-4 rounded-lg shadow transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-brand"
                    >
                        Pilih Paket Ini
                    </button>
                </div>
            </div>
        @empty
            <div class="col-span-full text-center py-10">
                <p class="text-gray-500">Belum ada paket membership yang tersedia saat ini.</p>
            </div>
        @endforelse

    </div>
</div>