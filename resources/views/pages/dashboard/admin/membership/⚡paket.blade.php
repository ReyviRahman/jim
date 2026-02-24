<?php

namespace App\Livewire\Membership;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Attributes\Computed;
use App\Models\User;
use App\Models\GymPackage;
use App\Models\Membership;
use Carbon\Carbon;

new #[Layout('layouts::admin')] class extends Component
{
    public User $user;

    // Form Inputs
    #[Validate('required|exists:gym_packages,id')]
    public $gym_package_id = '';

    public $with_pt = false; 

    #[Validate('nullable|exists:users,id')]
    public $pt_id = '';

    #[Validate('nullable|integer|min:0')]
    public $pt_price = 750000; 

    #[Validate('nullable|integer|min:1')]
    public $total_sessions = ''; 

    #[Validate('required|string|max:255')]
    public $member_goal = '';

    #[Validate('required|date')]
    public $start_date = '';

    #[Validate('required|date|after_or_equal:start_date')]
    public $end_date = '';

    // Properti Kalkulasi
    public $base_price = 0;
    public $discount_applied = 0; // Sekarang ini langsung menampung nominal diskon
    public $price_paid = 0;

    public function mount(User $user)
    {
        $this->user = $user;
        
        if ($this->hasActiveOrPendingMembership()) {
            session()->flash('error', "User {$user->name} masih memiliki membership yang aktif atau pending.");
            return redirect()->route('admin.membership.index');
        }

        $this->start_date = now()->format('Y-m-d');
        $this->end_date = now()->addDays(30)->format('Y-m-d');
    }

    private function hasActiveOrPendingMembership()
    {
        return Membership::where('user_id', $this->user->id)
            ->whereIn('status', ['active', 'pending'])
            ->exists();
    }

    #[Computed]
    public function packages()
    {
        return GymPackage::where('is_active', true)->latest()->get();
    }

    #[Computed]
    public function trainers()
    {
        return User::where('role', 'pt')->where('is_active', true)->get(); 
    }

    public function updated($property)
    {
        if (in_array($property, ['gym_package_id', 'with_pt', 'pt_price'])) {
            $this->calculateTotal();
        }

        if ($property === 'with_pt' && !$this->with_pt) {
            $this->pt_id = '';
            $this->pt_price = 750000;
            $this->total_sessions = '';
        }

        if ($property === 'start_date' && $this->start_date) {
            $this->end_date = Carbon::parse($this->start_date)->addDays(30)->format('Y-m-d');
        }
    }

    public function calculateTotal()
    {
        if ($this->gym_package_id) {
            $package = GymPackage::find($this->gym_package_id);
            
            if ($package) {
                // 1. Ambil Harga Paket Asli
                $this->base_price = $package->price;
                
                // 2. Ambil Nominal Diskon langsung dari kolom 'discount' di tabel gym_packages
                $this->discount_applied = $package->discount ?? 0;
                
                // 3. Kurangi Harga Dasar dengan Diskon
                $paketSetelahDiskon = $this->base_price - $this->discount_applied;

                // 4. Tambahkan dengan Harga PT
                $hargaPt = $this->with_pt ? (float) ($this->pt_price ?: 0) : 0;
                $this->price_paid = $paketSetelahDiskon + $hargaPt;

                // Pastikan total tidak minus
                if ($this->price_paid < 0) $this->price_paid = 0;
            }
        } else {
            $this->base_price = 0;
            $this->discount_applied = 0;
            $this->price_paid = 0;
        }
    }

    public function getFormattedDate($date)
    {
        if (!$date) return '';
        Carbon::setLocale('id');
        return Carbon::parse($date)->translatedFormat('l, d F Y');
    }

    public function save()
    {
        if ($this->hasActiveOrPendingMembership()) {
            session()->flash('error', "Pendaftaran dibatalkan: {$this->user->name} sudah memiliki membership aktif/pending di tab lain.");
            return redirect()->route('admin.membership.index');
        }

        if ($this->with_pt) {
            $this->validate([
                'pt_id' => 'required|exists:users,id',
                'pt_price' => 'required|numeric|min:0',
                'total_sessions' => 'required|integer|min:1',
            ]);
        }

        $this->validate();
        $this->calculateTotal();

        Membership::create([
            'user_id' => $this->user->id,
            'gym_package_id' => $this->gym_package_id,
            
            'pt_id' => $this->with_pt ? $this->pt_id : null,
            'total_sessions' => $this->with_pt ? $this->total_sessions : null,
            'remaining_sessions' => $this->with_pt ? $this->total_sessions : null,
            
            'base_price' => $this->base_price,
            // Hapus discount_percentage, sisa discount_applied saja
            'discount_applied' => $this->discount_applied,
            'price_paid' => $this->price_paid,
            
            'member_goal' => $this->member_goal,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'status' => 'active',
        ]);

        session()->flash('success', 'Membership berhasil didaftarkan!');
        return redirect()->to(route('admin.membership.index')); 
    }
};
?>

<div>
    <div class="mb-6">
        <h5 class="text-xl font-semibold text-heading mb-2">Pendaftaran Membership</h5>
        <p class="text-body text-sm">Pilih paket dan tentukan detail program untuk member.</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        {{-- KOLOM KIRI: Form Input --}}
        <div class="lg:col-span-2 space-y-6">
            
            {{-- Kartu Profil Singkat User --}}
            <div class="flex items-center p-4 bg-neutral-primary-soft shadow-xs rounded-md border border-default">
                @if($user->photo)
                    <img class="w-12 h-12 rounded-full object-cover" src="{{ asset('storage/' . $user->photo) }}" alt="{{ $user->name }}">
                @else
                    <img class="w-12 h-12 rounded-full object-cover" src="https://ui-avatars.com/api/?name={{ urlencode($user->name) }}&background=random" alt="{{ $user->name }}">
                @endif
                <div class="ps-4">
                    <div class="text-lg font-semibold text-heading">{{ $user->name }}</div>
                    <div class="text-sm text-body">{{ $user->email }} • {{ $user->occupation ?? 'Member' }}</div>
                </div>  
            </div>

            <form wire:submit="save" class="bg-white p-6 shadow-xs rounded-md border border-default">
                
                <div class="grid gap-6 mb-6 md:grid-cols-2">
                    
                    {{-- Pilih Paket --}}
                    <div class="md:col-span-2">
                        <label for="gym_package_id" class="block mb-2.5 text-sm font-medium text-heading">Pilih Paket Gym</label>
                        <select id="gym_package_id" wire:model.live="gym_package_id" class="bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-md focus:ring-brand focus:border-brand block w-full px-3 py-2.5 shadow-xs">
                            <option value="">-- Pilih Paket Membership --</option>
                            @foreach($this->packages as $package)
                                <option value="{{ $package->id }}">
                                    {{ $package->name }} (Rp {{ number_format($package->price, 0, ',', '.') }}) 
                                    {{-- Ubah tulisan diskon menjadi format Rupiah --}}
                                    @if($package->discount > 0) - Diskon Rp {{ number_format($package->discount, 0, ',', '.') }} @endif
                                </option>
                            @endforeach
                        </select>
                        @error('gym_package_id') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    {{-- Toggle PT --}}
                    <div class="md:col-span-2">
                        <label class="inline-flex items-center cursor-pointer">
                            <input type="checkbox" wire:model.live="with_pt" class="sr-only peer">
                            <div class="relative w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-brand-medium rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-brand"></div>
                            <span class="ms-3 text-sm font-medium text-heading">Gunakan Personal Trainer (PT)</span>
                        </label>
                    </div>

                    {{-- Conditional Input untuk PT --}}
                    @if($with_pt)
                        <div class="md:col-span-2 grid gap-6 p-4 bg-neutral-primary-soft rounded-md border border-default md:grid-cols-2">
                            {{-- Pilih PT --}}
                            <div class="md:col-span-2">
                                <label for="pt_id" class="block mb-2.5 text-sm font-medium text-heading">Pilih Personal Trainer</label>
                                <select id="pt_id" wire:model="pt_id" class="bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-md focus:ring-brand focus:border-brand block w-full px-3 py-2.5 shadow-xs">
                                    <option value="">-- Pilih Trainer --</option>
                                    @foreach($this->trainers as $trainer)
                                        <option value="{{ $trainer->id }}">{{ $trainer->name }}</option>
                                    @endforeach
                                </select>
                                @error('pt_id') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                            </div>

                            {{-- Harga PT --}}
                            <div>
                                <label for="pt_price" class="block mb-2.5 text-sm font-medium text-heading">Harga Jasa PT (Rp)</label>
                                <input type="number" id="pt_price" wire:model.live="pt_price" class="bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-md focus:ring-brand focus:border-brand block w-full px-3 py-2.5 shadow-xs">
                                @error('pt_price') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                            </div>

                            {{-- Jumlah Sesi --}}
                            <div>
                                <label for="total_sessions" class="block mb-2.5 text-sm font-medium text-heading">Jumlah Sesi</label>
                                <input type="number" id="total_sessions" wire:model.live="total_sessions" min="1" class="bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-md focus:ring-brand focus:border-brand block w-full px-3 py-2.5 shadow-xs" placeholder="Contoh: 12">
                                @error('total_sessions') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                            </div>
                        </div>
                    @endif

                    {{-- Target (Goal) --}}
                    <div class="md:col-span-2 mt-2">
                        <label for="member_goal" class="block mb-2.5 text-sm font-medium text-heading">Target (Goal) Member</label>
                        <input type="text" id="member_goal" wire:model="member_goal" placeholder="Contoh: Fat Loss, Bulking, Latihan Mandiri..." class="bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-md focus:ring-brand focus:border-brand block w-full px-3 py-2.5 shadow-xs">
                        @error('member_goal') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    {{-- Tanggal Mulai --}}
                    <div>
                        <label for="start_date" class="block mb-2.5 text-sm font-medium text-heading">Tanggal Mulai</label>
                        <input type="date" id="start_date" wire:model.live="start_date" class="bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-md focus:ring-brand focus:border-brand block w-full px-3 py-2.5 shadow-xs">
                        <p class="mt-1.5 text-xs text-brand-strong font-medium">{{ $this->getFormattedDate($start_date) }}</p>
                        @error('start_date') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    {{-- Tanggal Berakhir --}}
                    <div>
                        <label for="end_date" class="block mb-2.5 text-sm font-medium text-heading">Tanggal Berakhir</label>
                        <input type="date" id="end_date" wire:model.live="end_date" class="bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-md focus:ring-brand focus:border-brand block w-full px-3 py-2.5 shadow-xs">
                        <p class="mt-1.5 text-xs text-brand-strong font-medium">{{ $this->getFormattedDate($end_date) }}</p>
                        @error('end_date') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                </div>

            </form>
        </div>

        {{-- KOLOM KANAN: Ringkasan Pembayaran --}}
        <div>
            <div class="bg-neutral-primary-soft p-6 shadow-xs rounded-md border border-default sticky top-6">
                <h6 class="text-lg font-semibold text-heading mb-4 pb-4 border-b border-default-medium">Ringkasan Transaksi</h6>
                
                <div class="space-y-3 mb-6 text-sm text-body">
                    <div class="flex justify-between">
                        <span>Harga Paket Gym</span>
                        <span class="font-medium text-heading">Rp {{ number_format($base_price, 0, ',', '.') }}</span>
                    </div>
                    
                    {{-- Ubah tampilan diskon menjadi format nominal --}}
                    @if($discount_applied > 0)
                    <div class="flex justify-between text-green-600">
                        <span>Diskon Paket</span>
                        <span class="font-medium">- Rp {{ number_format($discount_applied, 0, ',', '.') }}</span>
                    </div>
                    @endif

                    @if($with_pt)
                    <div class="flex justify-between border-t border-default pt-3 mt-3">
                        <span>Harga PT ({{ $total_sessions ?: 0 }} Sesi)</span>
                        <span class="font-medium text-heading">Rp {{ number_format((float) $pt_price, 0, ',', '.') }}</span>
                    </div>
                    @else
                    <div class="flex justify-between border-t border-default pt-3 mt-3">
                        <span>Fasilitas</span>
                        <span class="font-medium text-heading">Gym Mandiri</span>
                    </div>
                    @endif
                </div>

                <div class="border-t border-default-medium pt-4 mb-6 flex justify-between items-center">
                    <span class="text-base font-semibold text-heading">Total Bayar</span>
                    <span class="text-xl font-bold text-brand-strong">Rp {{ number_format($price_paid, 0, ',', '.') }}</span>
                </div>

                <button 
                    type="button" 
                    wire:click="save"
                    class="w-full text-center text-black bg-brand box-border border border-transparent hover:bg-brand-strong focus:ring-4 focus:ring-brand-medium shadow-xs font-medium leading-5 rounded-md text-sm px-4 py-3 focus:outline-none disabled:opacity-50 transition-colors"
                    wire:loading.attr="disabled"
                >
                    <span wire:loading.remove>Konfirmasi Pembayaran</span>
                    <span wire:loading>Memproses...</span>
                </button>
            </div>
        </div>

    </div>
</div>