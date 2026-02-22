<?php

namespace App\Livewire\Member;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use App\Models\GymPackage;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

new #[Layout('layouts::member')] class extends Component
{
    use WithPagination;

    public GymPackage $package;
    public $selectedPtId = null;
    public $member_goal = '';

    public $start_date;
    public $end_date;

    public function mount(GymPackage $package)
    {
        $user = Auth::user();

        // 1. Lempar jika user masih punya membership aktif
        if ($user->activeMembership()) {
            session()->flash('error', 'Anda masih memiliki membership aktif. Tidak dapat membeli paket baru.');
            return $this->redirectRoute('member.paket.index', navigate: true); 
        }

        // 2. Lempar jika user masih punya transaksi pending
        if ($user->pendingMembership()) {
            session()->flash('error', 'Anda memiliki transaksi membership yang masih pending. Selesaikan atau batalkan terlebih dahulu.');
            return $this->redirectRoute('member.paket.index', navigate: true);
        }

        // Jika aman, lanjutkan inisialisasi properti
        $this->package = $package;
        $this->start_date = Carbon::now()->format('Y-m-d');
        $this->end_date = Carbon::now()->addMonth()->format('Y-m-d');
    }

    public function updatedStartDate($value)
    {
        $this->end_date = Carbon::parse($value)->addMonth()->format('Y-m-d');
    }

    public function getTrainers()
    {
        return User::where('role', 'pt')
                   ->where('is_active', true)
                   ->paginate(4); // Saya ubah jadi 4 agar grid pas jika opsi 'Tanpa PT' dihilangkan
    }

    public function selectPt($ptId)
    {
        $this->selectedPtId = ($this->selectedPtId === $ptId) ? null : $ptId;
    }

    public function store()
    {
        // 1. Setup aturan validasi dinamis
        $rules = [
            'member_goal' => 'required|string|min:5|max:255',
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after:start_date',
        ];

        // Jika paket PUNYA sesi, maka WAJIB pilih PT
        if ($this->package->number_of_sessions !== null) {
            $rules['selectedPtId'] = 'required|exists:users,id';
        } else {
            // Jika paket Mandiri, paksa selectedPtId jadi null
            $this->selectedPtId = null;
        }

        // 2. Jalankan validasi dengan pesan kustom
        $this->validate($rules, [
            'selectedPtId.required' => 'Anda wajib memilih Personal Trainer untuk paket ini.'
        ]);

        // 3. Pengecekan ganda keamanan
        $user = Auth::user();
        if ($user->activeMembership() || $user->pendingMembership()) {
            session()->flash('error', 'Transaksi gagal: Anda memiliki membership aktif atau pending.');
            return $this->redirectRoute('member.paket.index', navigate: true);
        }

        // 4. Simpan ke Database
        Membership::create([
            'user_id' => Auth::id(),
            'gym_package_id' => $this->package->id,
            'pt_id' => $this->selectedPtId,
            'price_paid' => $this->package->price,
            'total_sessions' => $this->package->number_of_sessions,
            'remaining_sessions' => $this->package->number_of_sessions,
            'member_goal' => $this->member_goal,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'status' => 'pending',
        ]);

        session()->flash('message', 'Pendaftaran berhasil!');
        return $this->redirectRoute('member.membership.index', navigate: true);
    }
};
?>

<div class="py-10 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto">
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        <div class="lg:col-span-1">
            <div class="bg-white rounded-2xl shadow-sm border border-default p-6 sticky top-6">
                <h3 class="text-lg font-semibold text-heading mb-4">Paket Pilihan Anda</h3>
                <div class="bg-neutral-primary-soft rounded-xl p-4 mb-6 relative overflow-hidden">
                    
                    {{-- Badge Indikator Tipe Paket --}}
                    <div class="mb-2">
                        @if($package->number_of_sessions)
                            <span class="inline-block px-2 py-1 text-[10px] uppercase tracking-wider font-bold text-blue-800 bg-blue-100 rounded-md">
                                💪 Paket + Coach
                            </span>
                        @else
                            <span class="inline-block px-2 py-1 text-[10px] uppercase tracking-wider font-bold text-gray-800 bg-gray-200 rounded-md">
                                🏃 Gym Mandiri
                            </span>
                        @endif
                    </div>

                    <h2 class="text-2xl font-bold text-brand">{{ $package->name }}</h2>
                    <p class="text-3xl font-bold text-heading mt-2">
                        Rp {{ number_format($package->price, 0, ',', '.') }}
                    </p>
                    
                    @if($package->number_of_sessions)
                        <p class="text-sm text-gray-500 mt-2 font-medium">{{ $package->number_of_sessions }} Sesi Latihan</p>
                    @endif
                </div>
                <a href="{{ route('member.paket.index') }}" wire:navigate class="text-sm text-brand hover:underline">
                    &larr; Ganti Paket
                </a>
            </div>
        </div>

        <div class="lg:col-span-2 space-y-8">
            
            {{-- HANYA TAMPILKAN JIKA PAKET MEMILIKI SESI (PAKET COACH) --}}
            @if($package->number_of_sessions !== null)
                <section>
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-xl font-bold text-heading">Pilih Personal Trainer</h3>
                        <span class="text-sm text-red-600 bg-red-100 font-semibold px-3 py-1 rounded-full">Wajib Dipilih</span>
                    </div>

                    @error('selectedPtId') 
                        <div class="mb-4 p-3 bg-red-50 border border-red-200 text-red-600 rounded-lg text-sm">
                            ⚠️ {{ $message }}
                        </div> 
                    @enderror

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                        @foreach($this->getTrainers() as $trainer)
                            <div 
                                wire:click="selectPt({{ $trainer->id }})"
                                class="group cursor-pointer border-2 rounded-2xl overflow-hidden transition-all relative
                                {{ $selectedPtId === $trainer->id ? 'border-brand bg-brand-light/10 ring-2 ring-brand' : 'border-gray-200 bg-white hover:border-brand' }}"
                            >
                                <div class="aspect-[4/3] w-full bg-brand-medium overflow-hidden">
                                    @if($trainer->photo)
                                        <img src="{{ asset('storage/' . $trainer->photo) }}" class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-110">
                                    @else
                                        <div class="w-full h-full flex items-center justify-center text-white text-5xl font-bold">
                                            {{ substr($trainer->name, 0, 1) }}
                                        </div>
                                    @endif
                                </div>
                                
                                <div class="p-4">
                                    <div class="flex justify-between items-center">
                                        <div>
                                            <h4 class="font-bold text-heading text-lg leading-tight">{{ $trainer->name }}</h4>
                                            <p class="text-sm text-body mt-1">{{ $trainer->age }} Tahun • {{ $trainer->gender }}</p>
                                        </div>
                                        @if($selectedPtId === $trainer->id)
                                            <div class="text-brand bg-white rounded-full"><svg class="w-7 h-7" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg></div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-8 brand-pagination">
                        {{ $this->getTrainers()->links() }}
                    </div>
                </section>
            @else
                {{-- BANNER INFORMASI JIKA PAKET MANDIRI --}}
                <div class="bg-gray-50 border border-gray-200 rounded-2xl p-5 flex items-start space-x-4">
                    <div class="flex-shrink-0 bg-white p-2 rounded-full shadow-sm">
                        <span class="text-2xl">🏃</span>
                    </div>
                    <div>
                        <h4 class="text-heading font-bold">Akses Gym Mandiri</h4>
                        <p class="text-sm text-body mt-1">Paket ini merupakan akses tanpa Personal Trainer. Anda bebas menggunakan seluruh fasilitas Gym secara mandiri selama masa aktif.</p>
                    </div>
                </div>
            @endif

            <section>
                <h3 class="text-xl font-bold text-heading mb-4">Durasi Membership</h3>
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-default grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-body mb-2">Tanggal Mulai Latihan</label>
                        <input 
                            type="date" 
                            wire:model.live="start_date" 
                            class="w-full rounded-xl border-gray-300 shadow-sm focus:border-brand focus:ring-brand"
                        >
                        @error('start_date') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-body mb-2">Tanggal Berakhir</label>
                        <input 
                            type="date" 
                            wire:model="end_date" 
                            class="w-full rounded-xl border-gray-300 shadow-sm focus:border-brand focus:ring-brand"
                        >
                        @error('end_date') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                    </div>
                </div>
            </section>

            <section>
                <h3 class="text-xl font-bold text-heading mb-4">Tujuan Latihan</h3>
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-default">
                    <textarea 
                        wire:model="member_goal" 
                        rows="4" 
                        class="w-full rounded-xl border-gray-300 shadow-sm focus:border-brand focus:ring-brand"
                        placeholder="Contoh: Menurunkan berat badan, menambah massa otot..."></textarea>
                    @error('member_goal') <span class="text-red-500 text-sm mt-1 block">{{ $message }}</span> @enderror
                </div>
            </section>

            <button 
                wire:click="store" 
                wire:loading.attr="disabled"
                class="w-full bg-brand hover:bg-brand-strong text-white font-bold py-4 px-6 rounded-xl shadow-lg transition-all transform hover:-translate-y-1 flex justify-center items-center"
            >
                <span wire:loading.remove>Konfirmasi & Daftar Sekarang</span>
                <span wire:loading>Memproses...</span>
            </button>
        </div>
    </div>
</div>