<?php

namespace App\Livewire\Member;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use App\Models\GymPackage;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

new #[Layout('layouts::member')] class extends Component
{
    public GymPackage $package; // Data paket dari URL otomatis di-inject
    
    // Form Input
    public $selectedPtId = null; // Bisa null jika ingin latihan mandiri
    public $member_goal = '';

    public function mount(GymPackage $package)
    {
        $this->package = $package;
    }

    // Ambil daftar user yang profesinya Personal Trainer (sesuaikan filter Anda)
    #[Computed]
    public function trainers()
    {
        // Asumsi di tabel users ada kolom 'occupation' atau role tertentu
        // Jika belum ada filter role, tampilkan user tertentu atau kosongkan logic ini
        return User::where('occupation', 'Personal Trainer') 
                   ->where('is_active', true)
                   ->get();
    }

    public function selectPt($ptId)
    {
        // Toggle: Jika diklik lagi, jadi unselect (null)
        $this->selectedPtId = ($this->selectedPtId === $ptId) ? null : $ptId;
    }

    public function store()
    {
        $this->validate([
            'member_goal' => 'required|string|min:5|max:255',
            'selectedPtId' => 'nullable|exists:users,id',
        ], [
            'member_goal.required' => 'Mohon isi tujuan latihan Anda.',
        ]);

        Membership::create([
            'user_id' => Auth::id(),
            'gym_package_id' => $this->package->id,
            'pt_id' => $this->selectedPtId, // Menyimpan ID PT (atau NULL)
            'price_paid' => $this->package->price,
            'total_sessions' => $this->package->number_of_sessions,
            'remaining_sessions' => $this->package->number_of_sessions,
            'member_goal' => $this->member_goal,
            'start_date' => Carbon::now(),
            'end_date' => Carbon::now()->addMonth(), // Default 1 bulan
            'status' => 'pending',
        ]);

        session()->flash('message', 'Paket berhasil dipilih! Silakan lakukan pembayaran.');

        // Redirect ke dashboard atau halaman invoice
        return $this->redirect('/dashboard', navigate: true);
    }
};
?>

<div class="py-10 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto">

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        <div class="lg:col-span-1">
            <div class="bg-white rounded-2xl shadow-sm border border-default p-6 sticky top-6">
                <h3 class="text-lg font-semibold text-heading mb-4">Paket Pilihan Anda</h3>
                
                <div class="bg-neutral-primary-soft rounded-xl p-4 mb-6">
                    <h2 class="text-2xl font-bold text-brand">{{ $package->name }}</h2>
                    <p class="text-3xl font-bold text-heading mt-2">
                        Rp {{ number_format($package->price, 0, ',', '.') }}
                    </p>
                    <p class="text-sm text-body mt-1">/ bulan</p>
                </div>

                <ul class="space-y-3 text-sm text-body mb-6">
                    <li class="flex items-center">
                        <svg class="w-5 h-5 text-green-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                        {{ $package->number_of_sessions }} Sesi Latihan
                    </li>
                    <li class="flex items-center">
                        <svg class="w-5 h-5 text-green-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                        Akses Penuh Gym
                    </li>
                </ul>

                <a href="{{ url()->previous() }}" wire:navigate class="text-sm text-brand hover:underline">
                    &larr; Ganti Paket
                </a>
            </div>
        </div>

        <div class="lg:col-span-2 space-y-8">
            
            <section>
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-xl font-bold text-heading">1. Pilih Personal Trainer</h3>
                    <span class="text-sm text-body bg-gray-100 px-3 py-1 rounded-full">Opsional</span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div 
                        wire:click="$set('selectedPtId', null)"
                        class="cursor-pointer border-2 rounded-xl p-4 flex items-center space-x-4 transition-all hover:shadow-md
                        {{ is_null($selectedPtId) ? 'border-brand bg-brand-light/10 ring-1 ring-brand' : 'border-gray-200 bg-white hover:border-brand' }}"
                    >
                        <div class="h-12 w-12 rounded-full bg-gray-200 flex items-center justify-center text-gray-500">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                        </div>
                        <div>
                            <h4 class="font-semibold text-heading">Latihan Mandiri</h4>
                            <p class="text-xs text-body">Tanpa instruktur khusus</p>
                        </div>
                        @if(is_null($selectedPtId))
                            <div class="ml-auto text-brand"><svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg></div>
                        @endif
                    </div>

                    @foreach($this->trainers as $trainer)
                        <div 
                            wire:click="selectPt({{ $trainer->id }})"
                            class="cursor-pointer border-2 rounded-xl p-4 flex items-center space-x-4 transition-all hover:shadow-md
                            {{ $selectedPtId === $trainer->id ? 'border-brand bg-brand-light/10 ring-1 ring-brand' : 'border-gray-200 bg-white hover:border-brand' }}"
                        >
                            <div class="h-12 w-12 rounded-full bg-brand-medium flex items-center justify-center text-white font-bold text-lg">
                                {{ substr($trainer->name, 0, 1) }}
                            </div>
                            
                            <div>
                                <h4 class="font-semibold text-heading">{{ $trainer->name }}</h4>
                                <p class="text-xs text-body">{{ $trainer->age }} Tahun • {{ $trainer->gender }}</p>
                            </div>

                            @if($selectedPtId === $trainer->id)
                                <div class="ml-auto text-brand"><svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg></div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </section>

            <section>
                <h3 class="text-xl font-bold text-heading mb-4">2. Tujuan Latihan</h3>
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-default">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Apa target yang ingin Anda capai? <span class="text-red-500">*</span>
                    </label>
                    <textarea 
                        wire:model="member_goal" 
                        rows="4" 
                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-brand focus:ring-brand"
                        placeholder="Contoh: Saya ingin menurunkan berat badan 5kg dalam 2 bulan dan melatih otot kaki."
                    ></textarea>
                    @error('member_goal') 
                        <span class="text-red-500 text-sm mt-1 block">{{ $message }}</span> 
                    @enderror
                </div>
            </section>

            <div class="pt-4">
                <button 
                    wire:click="store" 
                    wire:loading.attr="disabled"
                    class="w-full bg-brand hover:bg-brand-strong text-white font-bold py-4 px-6 rounded-xl shadow-lg transform transition hover:-translate-y-1 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-brand flex justify-center items-center"
                >
                    <span wire:loading.remove>Konfirmasi & Daftar Sekarang</span>
                    <span wire:loading><svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Memproses...</span>
                </button>
                <p class="text-center text-xs text-gray-500 mt-4">
                    Dengan mendaftar, Anda menyetujui syarat & ketentuan Gym kami.
                </p>
            </div>

        </div>
    </div>
</div>