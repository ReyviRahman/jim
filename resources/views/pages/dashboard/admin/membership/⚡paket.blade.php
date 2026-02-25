<?php

namespace App\Livewire\Admin; 

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use App\Models\User;
use App\Models\GymPackage;
use App\Models\Membership as MembershipModel;
use Carbon\Carbon;

new #[Layout('layouts::admin')] class extends Component
{
    public $selectedUsers; 
    public $mainUser; 

    // --- FORM INPUTS ---
    public $registration_type = ''; 
    
    // Gym Input
    public $gym_package_id = '';
    public $membership_end_date = '';
    
    // PT Input
    public $pt_package_id = ''; 
    public $pt_id = '';
    public $pt_end_date = '';

    // General Input
    public $member_goal = '';
    public $start_date = '';

    // Kalkulasi
    public $base_price = 0;
    public $discount_applied = 0; 
    public $price_paid = 0;
    
    public $calculated_total_sessions = 0;

    public function mount()
    {
        $userIds = request()->query('users', []);

        if (empty($userIds) || !is_array($userIds)) {
            session()->flash('error', 'Data user tidak valid atau kosong.');
            return redirect()->route('admin.membership.gabung');
        }

        $this->selectedUsers = User::whereIn('id', $userIds)->get();

        if ($this->selectedUsers->isEmpty()) {
            session()->flash('error', 'Data user tidak ditemukan.');
            return redirect()->route('admin.membership.gabung');
        }

        $this->mainUser = $this->selectedUsers->first();
        
        foreach ($this->selectedUsers as $u) {
            if ($this->hasActiveOrPendingMembership($u->id)) {
                session()->flash('error', "User {$u->name} masih memiliki membership yang aktif atau pending.");
                return redirect()->route('admin.membership.gabung');
            }
        }

        $this->start_date = now()->format('Y-m-d');
        $this->membership_end_date = now()->addDays(30)->format('Y-m-d');
        $this->pt_end_date = now()->addDays(30)->format('Y-m-d');
        
        $this->calculateTotal();
    }

    private function hasActiveOrPendingMembership($userId)
    {
        return User::find($userId)->memberships()
            ->whereIn('status', ['active', 'pending'])
            ->exists();
    }

    // --- PERBAIKAN DI SINI ---
    #[Computed]
    public function gymPackages()
    {
        $jumlahUser = $this->selectedUsers->count();
        
        // Ambil paket berdasarkan tipe (gym atau visit)
        if ($this->registration_type === 'visit') {
            $query = GymPackage::where('is_active', true)->where('type', 'visit');
        } else {
            $query = GymPackage::where('is_active', true)->where('type', 'gym');
            
            // Saring berdasarkan jumlah orang (category)
            if ($jumlahUser === 1) {
                $query->where('category', 'single');
            } elseif ($jumlahUser === 2) {
                $query->where('category', 'couple');
            } elseif ($jumlahUser >= 3) {
                $query->where('category', 'group')->where('max_members', '>=', $jumlahUser); 
            }
        }

        return $query->latest()->get();
    }

    #[Computed]
    public function ptPackages()
    {
        $jumlahUser = $this->selectedUsers->count();
        $query = GymPackage::where('is_active', true)->where('type', 'pt');

        // Saring berdasarkan jumlah orang (category) untuk PT
        if ($jumlahUser === 1) {
            $query->where('category', 'single');
        } elseif ($jumlahUser === 2) {
            $query->where('category', 'couple');
        } elseif ($jumlahUser >= 3) {
            $query->where('category', 'group')->where('max_members', '>=', $jumlahUser); 
        }

        return $query->latest()->get();
    }

    #[Computed]
    public function trainers()
    {
        return User::where('role', 'pt')->where('is_active', true)->get(); 
    }

    public function updated($property)
    {
        if ($property === 'registration_type') {
            // Reset semua input jika tipe berubah
            $this->pt_package_id = '';
            $this->pt_id = '';
            $this->gym_package_id = '';
            
            // Jika pilih visit, tanggal end disamakan dengan start
            if ($this->registration_type === 'visit') {
                $this->membership_end_date = $this->start_date;
            }
        }

        if (in_array($property, ['registration_type', 'gym_package_id', 'pt_package_id'])) {
            $this->calculateTotal();
        }

        if ($property === 'start_date' && $this->start_date) {
            // Jika visit, masa aktif hanya 1 hari (hari ini)
            if ($this->registration_type === 'visit') {
                $this->membership_end_date = $this->start_date;
            } else {
                $this->membership_end_date = Carbon::parse($this->start_date)->addDays(30)->format('Y-m-d');
            }
            $this->pt_end_date = Carbon::parse($this->start_date)->addDays(30)->format('Y-m-d');
        }
    }

    public function calculateTotal()
    {
        $hargaGym = 0;
        $diskonGym = 0;
        
        $hargaPt = 0;
        $diskonPt = 0;
        $this->calculated_total_sessions = 0; 

        // Visit juga menggunakan form/dropdown yang sama dengan Gym
        $isGymActive = in_array($this->registration_type, ['membership', 'bundle_pt_membership', 'visit']);
        if ($isGymActive && $this->gym_package_id) {
            $package = GymPackage::find($this->gym_package_id);
            if ($package) {
                $hargaGym = $package->price;
                $diskonGym = $package->discount ?? 0;
            }
        }

        $isPtActive = in_array($this->registration_type, ['pt', 'bundle_pt_membership']);
        if ($isPtActive && $this->pt_package_id) {
            $ptPackage = GymPackage::find($this->pt_package_id);
            if ($ptPackage) {
                $hargaPt = $ptPackage->price;
                $diskonPt = $ptPackage->discount ?? 0;
                $this->calculated_total_sessions = $ptPackage->pt_sessions; 
            }
        }

        $this->base_price = $hargaGym + $hargaPt; 
        $this->discount_applied = $diskonGym + $diskonPt;
        
        $this->price_paid = $this->base_price - $this->discount_applied; 

        if ($this->price_paid < 0) $this->price_paid = 0;
    }

    public function getFormattedDate($date)
    {
        if (!$date) return '';
        Carbon::setLocale('id');
        return Carbon::parse($date)->translatedFormat('l, d F Y');
    }

    public function save()
    {
        if (!$this->registration_type) {
            $this->addError('registration_type', 'Pilih jenis pendaftaran terlebih dahulu.');
            return;
        }

        // --- VALIDASI DIPERBARUI ---
        $rules = [
            'registration_type' => 'required|in:membership,pt,bundle_pt_membership,visit',
            'start_date' => 'required|date',
            'member_goal' => 'nullable|string|max:255',
        ];

        // Validasi untuk Gym Bulanan ATAU Harian (Visit)
        if (in_array($this->registration_type, ['membership', 'bundle_pt_membership', 'visit'])) {
            $rules['gym_package_id'] = 'required|exists:gym_packages,id';
            $rules['membership_end_date'] = 'required|date|after_or_equal:start_date';
        }

        if (in_array($this->registration_type, ['pt', 'bundle_pt_membership'])) {
            $rules['pt_package_id'] = 'required|exists:gym_packages,id'; 
            $rules['pt_id'] = 'required|exists:users,id';
            $rules['pt_end_date'] = 'required|date|after_or_equal:start_date';
        }

        $this->validate($rules);
        $this->calculateTotal();

        $membership = MembershipModel::create([
            'user_id' => $this->mainUser->id, 
            'type' => $this->registration_type,
            
            'gym_package_id' => in_array($this->registration_type, ['membership', 'bundle_pt_membership', 'visit']) ? $this->gym_package_id : null,
            'pt_package_id' => in_array($this->registration_type, ['pt', 'bundle_pt_membership']) ? $this->pt_package_id : null,
            'pt_id' => in_array($this->registration_type, ['pt', 'bundle_pt_membership']) ? $this->pt_id : null,
            
            'base_price' => $this->base_price,
            'discount_applied' => $this->discount_applied,
            'price_paid' => $this->price_paid,
            
            'total_sessions' => in_array($this->registration_type, ['pt', 'bundle_pt_membership']) ? $this->calculated_total_sessions : null,
            'remaining_sessions' => in_array($this->registration_type, ['pt', 'bundle_pt_membership']) ? $this->calculated_total_sessions : null,
            
            'member_goal' => $this->member_goal,
            'start_date' => $this->start_date,
            'membership_end_date' => in_array($this->registration_type, ['membership', 'bundle_pt_membership', 'visit']) ? $this->membership_end_date : null,
            'pt_end_date' => in_array($this->registration_type, ['pt', 'bundle_pt_membership']) ? $this->pt_end_date : null,
            
            'status' => 'active',
        ]);

        $membership->members()->attach($this->selectedUsers->pluck('id')->toArray());

        session()->flash('success', 'Membership berhasil didaftarkan untuk ' . $this->selectedUsers->count() . ' member!');
        return redirect()->to(route('admin.membership.index')); 
    }
}
?>

<div>
    <div class="mb-6">
        <h5 class="text-xl font-semibold text-heading mb-2">Pendaftaran Program</h5>
        <p class="text-body text-sm">Pilih jenis program dan tentukan detail untuk member.</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        {{-- KOLOM KIRI: Form Input --}}
        <div class="lg:col-span-2 space-y-6">
            
            {{-- Kartu Profil Singkat User --}}
            <div class="p-4 bg-neutral-primary-soft shadow-xs rounded-md border border-default">
                <h6 class="text-sm font-semibold text-heading mb-3 pb-2 border-b border-default-medium">Member yang Didaftarkan:</h6>
                <div class="space-y-4">
                    @foreach($selectedUsers as $index => $u)
                        <div class="flex items-center">
                            @if($u->photo)
                                <img class="w-12 h-12 rounded-full object-cover" src="{{ asset('storage/' . $u->photo) }}" alt="{{ $u->name }}">
                            @else
                                <img class="w-12 h-12 rounded-full object-cover" src="https://ui-avatars.com/api/?name={{ urlencode($u->name) }}&background=random" alt="{{ $u->name }}">
                            @endif
                            <div class="ps-4">
                                <div class="text-lg font-semibold text-heading flex items-center gap-2">
                                    {{ $u->name }}
                                </div>
                                <div class="text-sm text-body">{{ $u->email }} • {{ $u->occupation ?? 'Member' }}</div>
                            </div>  
                        </div>
                    @endforeach
                </div>
            </div>

            <form wire:submit="save" class="bg-white p-6 shadow-xs rounded-md border border-default">
                
                <div class="grid gap-6 mb-6 md:grid-cols-2">
                    
                    {{-- 1. PILIH JENIS PENDAFTARAN UTAMA --}}
                    <div class="md:col-span-2 pb-4 border-b border-default-medium">
                        <label for="registration_type" class="block mb-2.5 text-sm font-semibold text-brand-strong">1. Pilih Jenis Pendaftaran</label>
                        <select id="registration_type" wire:model.live="registration_type" class="bg-white border border-brand-medium text-heading text-sm rounded-md focus:ring-brand focus:border-brand block w-full px-3 py-3 shadow-sm font-medium">
                            <option value="">-- Silakan Pilih Jenis Program --</option>
                            <option value="visit">🎟️ Visit / Harian</option> {{-- OPSI BARU --}}
                            <option value="membership">🏋️ Membership Gym Only</option>
                            <option value="pt">👨‍🏫 Personal Trainer Only</option>
                            {{-- <option value="bundle_pt_membership">⭐ Bundle Gym + Personal Training</option> --}}
                        </select>
                        @error('registration_type') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    @if($registration_type)
                        {{-- Tanggal Mulai & Goal --}}
                        <div class="md:col-span-2 grid gap-6 md:grid-cols-2">
                            <div>
                                <label for="start_date" class="block mb-2.5 text-sm font-medium text-heading">Tanggal Mulai Program</label>
                                <input type="date" id="start_date" wire:model.live="start_date" class="bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-md focus:ring-brand focus:border-brand block w-full px-3 py-2.5 shadow-xs">
                                <p class="mt-1.5 text-xs text-brand-strong font-medium">{{ $this->getFormattedDate($start_date) }}</p>
                                @error('start_date') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label for="member_goal" class="block mb-2.5 text-sm font-medium text-heading">Target (Goal) Member</label>
                                <input type="text" id="member_goal" wire:model="member_goal" placeholder="Contoh: Fat Loss, Bulking..." class="bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-md focus:ring-brand focus:border-brand block w-full px-3 py-2.5 shadow-xs">
                                @error('member_goal') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        {{-- 2. FORM MEMBERSHIP GYM (TERMASUK VISIT) --}}
                        @if(in_array($registration_type, ['membership', 'bundle_pt_membership', 'visit']))
                        <div class="md:col-span-2 mt-4 p-4 bg-gray-50 rounded-md border border-gray-200">
                            <h6 class="text-sm font-semibold text-heading mb-4 border-b border-gray-200 pb-2">Detail {{ $registration_type === 'visit' ? 'Kunjungan Harian' : 'Membership Gym' }}</h6>
                            <div class="grid gap-6 md:grid-cols-2">
                                <div class="md:col-span-2">
                                    <label for="gym_package_id" class="block mb-2.5 text-sm font-medium text-heading">Pilih Paket {{ $registration_type === 'visit' ? 'Visit' : 'Gym' }}</label>
                                    <select id="gym_package_id" wire:model.live="gym_package_id" class="bg-white border border-default-medium text-heading text-sm rounded-md focus:ring-brand focus:border-brand block w-full px-3 py-2.5 shadow-xs">
                                        <option value="">-- Pilih Paket --</option>
                                        @foreach($this->gymPackages as $package)
                                            <option value="{{ $package->id }}">
                                                {{ $package->name }} (Rp {{ number_format($package->price, 0, ',', '.') }}) 
                                                @if($package->discount > 0) - Diskon Rp {{ number_format($package->discount, 0, ',', '.') }} @endif
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('gym_package_id') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                                </div>
                                
                                {{-- Jika visit, sembunyikan input tanggal berakhir karena hari itu juga selesai --}}
                                <div class="{{ $registration_type === 'visit' ? 'hidden' : '' }}">
                                    <label for="membership_end_date" class="block mb-2.5 text-sm font-medium text-heading">Tanggal Berakhir Gym</label>
                                    <input type="date" id="membership_end_date" wire:model.live="membership_end_date" class="bg-white border border-default-medium text-heading text-sm rounded-md focus:ring-brand focus:border-brand block w-full px-3 py-2.5 shadow-xs">
                                    <p class="mt-1.5 text-xs text-brand-strong font-medium">{{ $this->getFormattedDate($membership_end_date) }}</p>
                                    @error('membership_end_date') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                                </div>
                            </div>
                        </div>
                        @endif

                        {{-- 3. FORM PERSONAL TRAINER --}}
                        @if(in_array($registration_type, ['pt', 'bundle_pt_membership']))
                        <div class="md:col-span-2 mt-4 p-4 bg-blue-50 rounded-md border border-blue-100">
                            <h6 class="text-sm font-semibold text-blue-800 mb-4 border-b border-blue-200 pb-2">Detail Personal Trainer (PT)</h6>
                            <div class="grid gap-6 md:grid-cols-2">
                                
                                {{-- Pilih Paket PT (Dari Database) --}}
                                <div class="md:col-span-2">
                                    <label for="pt_package_id" class="block mb-2.5 text-sm font-medium text-heading">Pilih Paket Layanan PT</label>
                                    <select id="pt_package_id" wire:model.live="pt_package_id" class="bg-white border border-blue-300 text-blue-900 text-sm rounded-md focus:ring-blue-500 focus:border-blue-500 block w-full px-3 py-2.5 shadow-xs">
                                        <option value="">-- Pilih Paket PT --</option>
                                        @foreach($this->ptPackages as $package)
                                            <option value="{{ $package->id }}">
                                                {{ $package->name }} [{{ $package->pt_sessions }} Sesi] (Rp {{ number_format($package->price, 0, ',', '.') }})
                                                @if($package->discount > 0) - Diskon Rp {{ number_format($package->discount, 0, ',', '.') }} @endif
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('pt_package_id') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                                </div>

                                <div>
                                    <label for="pt_id" class="block mb-2.5 text-sm font-medium text-heading">Pilih Personal Trainer</label>
                                    <select id="pt_id" wire:model.live="pt_id" class="bg-white border border-default-medium text-heading text-sm rounded-md focus:ring-brand focus:border-brand block w-full px-3 py-2.5 shadow-xs">
                                        <option value="">-- Pilih Trainer --</option>
                                        @foreach($this->trainers as $trainer)
                                            <option value="{{ $trainer->id }}">{{ $trainer->name }}</option>
                                        @endforeach
                                    </select>
                                    @error('pt_id') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                                </div>
                                
                                <div>
                                    <label for="pt_end_date" class="block mb-2.5 text-sm font-medium text-heading">Tanggal Kedaluwarsa Sesi PT</label>
                                    <input type="date" id="pt_end_date" wire:model.live="pt_end_date" class="bg-white border border-default-medium text-heading text-sm rounded-md focus:ring-brand focus:border-brand block w-full px-3 py-2.5 shadow-xs">
                                    <p class="mt-1.5 text-xs text-brand-strong font-medium">{{ $this->getFormattedDate($pt_end_date) }}</p>
                                    @error('pt_end_date') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                                </div>
                            </div>
                        </div>
                        @endif

                    @else
                        {{-- State Kosong --}}
                        <div class="md:col-span-2 text-center py-8 text-gray-400">
                            Silakan pilih jenis pendaftaran di atas untuk menampilkan form.
                        </div>
                    @endif

                </div>
            </form>
        </div>

        {{-- KOLOM KANAN: Ringkasan Pembayaran --}}
        <div>
            <div class="bg-neutral-primary-soft p-6 shadow-xs rounded-md border border-default sticky top-6">
                <h6 class="text-lg font-semibold text-heading mb-4 pb-4 border-b border-default-medium">Ringkasan Transaksi</h6>
                
                <div class="space-y-3 mb-6 text-sm text-body">
                    
                    @if(!$registration_type)
                        <div class="flex justify-between text-gray-400">
                            <span>Belum ada layanan dipilih</span>
                            <span>Rp 0</span>
                        </div>
                    @endif

                    @if(in_array($registration_type, ['membership', 'bundle_pt_membership']) && $gym_package_id)
                        @php
                            $gymPkg = App\Models\GymPackage::find($gym_package_id);
                        @endphp
                        @if($gymPkg)
                            <div class="flex justify-between">
                                <span>Paket Gym</span>
                                <span class="font-medium text-heading">Rp {{ number_format($gymPkg->price, 0, ',', '.') }}</span>
                            </div>
                            @if($gymPkg->discount > 0)
                                <div class="flex justify-between text-green-600">
                                    <span>Diskon Paket Gym</span>
                                    <span class="font-medium">- Rp {{ number_format($gymPkg->discount, 0, ',', '.') }}</span>
                                </div>
                            @endif
                        @endif
                    @endif

                    @if(in_array($registration_type, ['pt', 'bundle_pt_membership']) && $pt_package_id)
                        <div class="border-t border-default-medium my-2"></div>
                        @php
                            $ptPkg = App\Models\GymPackage::find($pt_package_id);
                        @endphp
                        @if($ptPkg)
                            <div class="flex justify-between">
                                <span>Paket PT ({{ $ptPkg->pt_sessions }} Sesi)</span>
                                <span class="font-medium text-heading">Rp {{ number_format($ptPkg->price, 0, ',', '.') }}</span>
                            </div>
                            @if($ptPkg->discount > 0)
                                <div class="flex justify-between text-green-600">
                                    <span>Diskon Paket PT</span>
                                    <span class="font-medium">- Rp {{ number_format($ptPkg->discount, 0, ',', '.') }}</span>
                                </div>
                            @endif
                        @endif
                    @endif

                </div>

                <div class="border-t border-default-medium pt-4 mb-6 flex flex-col items-end">
                    <span class="text-xs text-gray-500 mb-1">Total Tagihan:</span>
                    <span class="text-2xl font-bold text-brand-strong">Rp {{ number_format($price_paid, 0, ',', '.') }}</span>
                </div>

                <button 
                    type="button" 
                    wire:click="save"
                    class="w-full text-center text-black bg-brand hover:bg-brand-strong focus:ring-4 focus:ring-brand-medium font-medium rounded-md text-sm px-4 py-3 transition-colors disabled:opacity-50"
                    wire:loading.attr="disabled"
                >
                    <span wire:loading.remove>Konfirmasi Pembayaran</span>
                    <span wire:loading>Memproses...</span>
                </button>
            </div>
        </div>

    </div>
</div>