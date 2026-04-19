<?php

namespace App\Livewire\Admin; 

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use App\Models\User;
use App\Models\GymPackage;
use App\Models\Membership as MembershipModel;
use App\Models\MembershipTransaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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
    public $start_date = '';
    public $is_active = true; // Status aktif/tidak aktif membership

    // Kalkulasi Harga
    public $base_price = 0;
    public $manual_discount = 0;
    public $discount_applied = 0; 
    public $price_paid = 0; // Total Tagihan Akhir
    public $calculated_total_sessions = 0;

    // Pembayaran & Transaksi
    public $payment_type = 'paid'; // 'paid' (Lunas) atau 'partial' (Nyicil)
    public $amount_paid = 0; // Uang yang dibayar SEKARANG
    public $payment_method = 'cash';
    public $payment_date = '';
    public $transaction_type = '';
    public $package_name = '';
    public $notes = '';

    public $admin_id = '';
    public $follow_up_id = '';
    public $follow_up_id_two = '';

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
        $this->payment_date = now()->format('Y-m-d');
        
        // Gunakan hitungan 30 hari kaku (tambah 29 hari karena hari ini sudah dihitung 1 hari)
        $this->membership_end_date = now()->addMonthsNoOverflow(1)->format('Y-m-d');
        $this->pt_end_date = now()->addMonthsNoOverflow(1)->format('Y-m-d');
        
        $this->calculateTotal();
    }

    private function hasActiveOrPendingMembership($userId)
    {
        return User::find($userId)->memberships()
            ->whereIn('status', ['active', 'pending'])
            ->exists();
    }

    #[Computed]
    public function adminUsers()
    {
        // Sesuaikan 'admin', 'kasir' dengan role yang ada di sistemmu
        return User::whereIn('role', ['kasir_gym'])->where('is_active', true)->get();
    }

    #[Computed]
    public function followUpUsers()
    {
        // Sesuaikan dengan role staff follow up di sistemmu
        return User::whereIn('role', ['pt', 'kasir_gym', 'sales'])->where('is_active', true)->get();
    }

    #[Computed]
    public function gymPackages()
    {
        $jumlahUser = $this->selectedUsers->count();
        
        if ($this->registration_type === 'visit') {
            $query = GymPackage::where('is_active', true)->where('type', 'visit');
        } else {
            $query = GymPackage::where('is_active', true)->where('type', 'gym');
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

    // HITUNG DURASI PROGRAM OTOMATIS (FORMAT BULAN & HARI)
    #[Computed]
    public function programDuration()
    {
        if (!$this->start_date) return '-';

        $start = Carbon::parse($this->start_date)->startOfDay();
        $end = null;

        if (in_array($this->registration_type, ['membership', 'bundle_pt_membership', 'visit']) && $this->membership_end_date) {
        $end = Carbon::parse($this->membership_end_date)->startOfDay();
        } elseif ($this->registration_type === 'pt' && $this->pt_end_date) {
        $end = Carbon::parse($this->pt_end_date)->startOfDay();
        }

        if ($end) {
        if ($this->registration_type === 'visit') {
            return '1 Hari (Visit Harian)';
        }

        // MENGGUNAKAN CARBON DIFF (Sesuai Kalender Asli)
        $diff = $start->diff($end);
        
        // Konversi tahun ke bulan (jika langganan lebih dari 1 tahun)
        $months = ($diff->y * 12) + $diff->m;
        $days = $diff->d;

        // Gabungkan menjadi teks
        $parts = [];
        if ($months > 0) $parts[] = $months . ' Bulan';
        if ($days > 0) $parts[] = $days . ' Hari';

        if (empty($parts)) {
            return 'Berakhir hari ini';
        }

        return implode(' ', $parts);
        }

        return '-';
    }

    public function updated($property)
    {
        if ($property === 'registration_type') {
            $this->pt_package_id = '';
            $this->pt_id = '';
            $this->gym_package_id = '';
            
            if ($this->registration_type === 'visit') {
                $this->membership_end_date = $this->start_date;
                $this->payment_type = 'paid'; // Visit wajib lunas
            }
        }

        if (in_array($property, ['registration_type', 'gym_package_id', 'pt_package_id', 'manual_discount'])) {
            $this->calculateTotal();
        }

        if ($property === 'start_date' && $this->start_date) {
            if ($this->registration_type === 'visit') {
                $this->membership_end_date = $this->start_date;
            } else {
                // GANTI addDays(29) menjadi addMonthsNoOverflow(1)
                $this->membership_end_date = Carbon::parse($this->start_date)->addMonthsNoOverflow(1)->format('Y-m-d');
            }
 $this->pt_end_date = Carbon::parse($this->start_date)->addMonthsNoOverflow(1)->format('Y-m-d');
        }

        if ($property === 'payment_type') {
            if ($this->payment_type === 'paid') {
                $this->amount_paid = $this->price_paid;
            } else {
                $this->amount_paid = ''; // Kosongkan agar kasir input manual jika nyicil
            }
        }

        if ($property === 'is_active') {
            // Jika user ingin set ke tidak aktif, paksa payment_type ke partial
            if (!$this->is_active) {
                $this->payment_type = 'partial';
                $this->amount_paid = '';
                // Null kan semua tanggal saat tidak aktif
                $this->start_date = null;
                $this->membership_end_date = null;
                $this->pt_end_date = null;
            }
        }
    }

    public function calculateTotal()
    {
        $hargaGym = 0;
        $diskonGym = 0;
        
        $hargaPt = 0;
        $diskonPt = 0;
        $this->calculated_total_sessions = 0; 
        $jumlahUser = $this->selectedUsers->count();

        $isGymActive = in_array($this->registration_type, ['membership', 'bundle_pt_membership', 'visit']);
        if ($isGymActive && $this->gym_package_id) {
            $package = GymPackage::find($this->gym_package_id);
            if ($package) {
                // Visit dihitung per orang, Gym paket (single/couple) harganya sudah pas
                $multiplier = ($this->registration_type === 'visit') ? $jumlahUser : 1;
                $hargaGym = $package->price * $multiplier;
                $diskonGym = ($package->discount ?? 0) * $multiplier;
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
        $diskonManualAngka = empty($this->manual_discount) ? 0 : (int) $this->manual_discount;
        $this->discount_applied = $diskonGym + $diskonPt + $diskonManualAngka;
        
        $this->price_paid = $this->base_price - $this->discount_applied; 
        if ($this->price_paid < 0) $this->price_paid = 0;

        // Auto isi jumlah bayar jika lunas
        if ($this->payment_type === 'paid') {
            $this->amount_paid = $this->price_paid;
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
        if (!$this->registration_type) {
            $this->addError('registration_type', 'Pilih jenis pendaftaran terlebih dahulu.');
            return;
        }

        if ($this->registration_type === 'visit' && $this->selectedUsers->count() > 1) {
            $this->addError('registration_type', 'Paket Visit / Harian hanya dapat didaftarkan untuk 1 orang per transaksi.');
            return;
        }

        $rules = [
            'registration_type' => 'required|in:membership,pt,bundle_pt_membership,visit',
            'start_date' => $this->is_active ? 'required|date' : 'nullable|date',
            'payment_type' => 'required|in:paid,partial',
            'payment_method' => 'required|in:cash,transfer,qris,debit',
            'payment_date' => 'required|date',
            'transaction_type' => 'required|string',
            'package_name' => 'required|string',
            'notes' => 'required|string',
            'admin_id' => 'required|exists:users,id',
            'follow_up_id' => 'required|exists:users,id',
            'follow_up_id_two' => 'required|exists:users,id',
            'is_active' => 'required|boolean',
            'manual_discount' => 'nullable|numeric|min:0|max:' . $this->base_price,
        ];

        // Validasi Nominal Nyicil
        if ($this->payment_type === 'partial') {
            $rules['amount_paid'] = 'required|numeric|min:1|max:' . ($this->price_paid - 1);
        }

        if (in_array($this->registration_type, ['membership', 'bundle_pt_membership', 'visit'])) {
            $rules['gym_package_id'] = 'required|exists:gym_packages,id';
            $rules['membership_end_date'] = $this->is_active ? 'required|date|after_or_equal:start_date' : 'nullable|date';
        }

        if (in_array($this->registration_type, ['pt', 'bundle_pt_membership'])) {
            $rules['pt_package_id'] = 'required|exists:gym_packages,id'; 
            $rules['pt_id'] = 'required|exists:users,id';
            $rules['pt_end_date'] = $this->is_active ? 'required|date|after_or_equal:start_date' : 'nullable|date';
        }

        $this->validate($rules, [
            'amount_paid.max' => 'Nominal cicilan tidak boleh lebih atau sama dengan total tagihan.',
            'amount_paid.min' => 'Nominal cicilan harus lebih dari 0.',
            'manual_discount.max' => 'Diskon tidak boleh melebihi total harga paket.',
        ]);

        foreach ($this->selectedUsers as $u) {
            if ($this->hasActiveOrPendingMembership($u->id)) {
                session()->flash('error', "Pendaftaran dibatalkan: User {$u->name} masih memiliki paket yang aktif atau pending.");
                return redirect()->route('admin.membership.index'); // Sesuaikan rute kembalinya

            }
        }
        
        $this->calculateTotal();

        // Nominal aktual yang dibayarkan saat ini
        $actualAmountPaid = $this->payment_type === 'paid' ? $this->price_paid : $this->amount_paid;

        // Ambil package data berdasarkan tipe registrasi
        $pkt = null;
        if (in_array($this->registration_type, ['membership', 'bundle_pt_membership', 'visit']) && $this->gym_package_id) {
            $pkt = GymPackage::find($this->gym_package_id);
        } elseif ($this->registration_type === 'pt' && $this->pt_package_id) {
            $pkt = GymPackage::find($this->pt_package_id);
        }

        // 👇 MULAI DATABASE TRANSACTION DI SINI 👇
        try {
            DB::beginTransaction();

            // 1. BUAT KONTRAK MEMBERSHIP
            $membership = MembershipModel::create([
                'user_id' => $this->mainUser->id, 
                'type' => $this->registration_type,
                
                'gym_package_id' => in_array($this->registration_type, ['membership', 'bundle_pt_membership', 'visit']) ? $this->gym_package_id : null,
                'pt_package_id' => in_array($this->registration_type, ['pt', 'bundle_pt_membership']) ? $this->pt_package_id : null,
                'pt_id' => in_array($this->registration_type, ['pt', 'bundle_pt_membership']) ? $this->pt_id : null,
                'admin_id' => $this->admin_id,
                'follow_up_id' => $this->follow_up_id ?: null,
                'follow_up_id_two' => $this->follow_up_id_two ?: null,
                'base_price' => $this->base_price,
                'discount_applied' => $this->discount_applied,
                'normal_price' => $pkt?->normal_price,
                'net_price' => $pkt?->net_price,
                'unrecommended_price' => $pkt?->unrecommended_price,
                'price_paid' => $this->price_paid, 
                
                'total_paid' => $actualAmountPaid, 
                'payment_status' => $this->payment_type, 
                
                'total_sessions' => in_array($this->registration_type, ['pt', 'bundle_pt_membership']) ? $this->calculated_total_sessions : null,
                'remaining_sessions' => in_array($this->registration_type, ['pt', 'bundle_pt_membership']) ? $this->calculated_total_sessions : null,
                
                'start_date' => $this->start_date,
                'membership_end_date' => in_array($this->registration_type, ['membership', 'bundle_pt_membership', 'visit']) ? $this->membership_end_date : null,
                'pt_end_date' => in_array($this->registration_type, ['pt', 'bundle_pt_membership']) ? $this->pt_end_date : null,
                
                'status' => $this->payment_type === 'paid' ? 'active' : 'pending',
                'is_active' => $this->is_active,
                'notes' => $this->notes,
            ]);

            $membership->members()->attach($this->selectedUsers->pluck('id')->toArray());

            // 2. CATAT TRANSAKSI KEUANGAN KASIR
            $packageNameStr = 'Paket Custom';
            if ($this->registration_type === 'visit') $packageNameStr = 'Visit Harian';
            elseif ($this->gym_package_id) $packageNameStr = GymPackage::find($this->gym_package_id)->name ?? 'Paket Gym';
            elseif ($this->pt_package_id) $packageNameStr = GymPackage::find($this->pt_package_id)->name ?? 'Paket PT';

            MembershipTransaction::create([
                'invoice_number' => 'INV-' . date('Ymd') . '-' . strtoupper(uniqid()),
                'membership_id' => $membership->id,
                'user_id' => $this->mainUser->id,
                'admin_id' => $this->admin_id, 
                'follow_up_id' => $this->follow_up_id ?: null,
                'follow_up_id_two' => $this->follow_up_id_two ?: null,
                'transaction_type' => $this->transaction_type,
                'package_name' => $this->package_name,
                'amount' => $actualAmountPaid,
                'payment_method' => $this->payment_method,
                'payment_date' => $this->payment_date,
                'start_date' => $this->start_date,
                'end_date' => in_array($this->registration_type, ['pt']) ? $this->pt_end_date : $this->membership_end_date,
                'notes' => $this->notes,
            ]);

            // Jika semua berhasil, simpan permanen ke database
            DB::commit();

            session()->flash('success', 'Transaksi berhasil disimpan dan uang masuk sudah dicatat.');
            return $this->redirectRoute('admin.penjualan.index', navigate: true); 

        } catch (\Exception $e) {
            // Jika ada gagal/error/putus di tengah jalan, batalkan semua insert data
            DB::rollBack();
            
            // Tampilkan pesan error ke layar agar kasir tahu
            session()->flash('error', 'Terjadi kesalahan sistem saat memproses transaksi: ' . $e->getMessage());
            return;
        }
    }
}
?>

<div>
    @if (session()->has('error'))
        <div class="mb-4 p-4 text-sm text-red-800 rounded-lg bg-red-50 border border-red-200">
            {{ session('error') }}
        </div>
    @endif
    <div class="mb-6 flex items-end gap-2">
        <a href="{{ route('admin.akun.member.index') }}" wire:navigate class="p-2 bg-white border border-default rounded-md hover:bg-gray-50 text-gray-600 transition-colors">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
        </a>
        <div>
            <h5 class="text-xl font-semibold text-heading mb-2">Pendaftaran & Transaksi Kasir</h5>
            <p class="text-body text-sm">Pilih jenis program, tentukan durasi, dan catat pembayaran.</p>
        </div>
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
                        <label for="registration_type" class="block mb-2.5 text-sm font-semibold text-brand-strong">Pilih Jenis Pendaftaran</label>
                        <select id="registration_type" wire:model.live="registration_type" class="bg-white border border-brand-medium text-heading text-sm rounded-md focus:ring-brand focus:border-brand block w-full px-3 py-3 shadow-sm font-medium">
                            <option value="">-- Silakan Pilih Jenis Program --</option>
                            @if($selectedUsers->count() > 1)
                                <option value="visit" disabled class="text-gray-400">🎟️ Visit / Harian (Hanya bisa 1 orang per transaksi)</option>
                            @else
                                <option value="visit">🎟️ Visit / Harian</option>
                            @endif
                            <option value="membership">🏋️ Membership Gym Only</option>
                            <option value="pt">👨‍🏫 Personal Trainer Only</option>
                        </select>
                        @error('registration_type') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    @if($registration_type)
                        {{-- 2. STATUS AKTIF / TIDAK AKTIF --}}
                        <div class="md:col-span-2 pb-4 border-b border-default-medium">
                            <label class="block mb-2.5 text-sm font-semibold text-brand-strong">Status Keanggotaan</label>
                            <div class="flex gap-6">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="radio" wire:model.live="is_active" value="1" {{ $is_active ? 'checked' : '' }} class="text-brand focus:ring-brand w-4 h-4">
                                    <span class="text-sm font-medium text-heading">✅ Aktif Sekarang</span>
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="radio" wire:model.live="is_active" value="0" {{ !$is_active ? 'checked' : '' }} class="text-red-500 focus:ring-red-500 w-4 h-4">
                                    <span class="text-sm font-medium text-heading">❌ Tidak Aktif (Pending)</span>
                                </label>
                            </div>
                            <p class="text-xs text-body mt-2">Jika "Tidak Aktif", member akan masuk status pending dan tanggal tidak perlu diisi hingga aktivasi.</p>
                        </div>

                        {{-- 3. DURASI PROGRAM (SEKARANG BERDAMPINGAN: MULAI, BERAKHIR, DURASI) --}}
                        <div class="md:col-span-2" {{ !$this->is_active ? 'style=display:none' : '' }}>
                            <h6 class="text-sm font-semibold text-heading mb-3">Durasi Program & Tanggal Aktif</h6>
                            <div class="grid gap-4 md:grid-cols-3 bg-gray-50 p-4 rounded border border-gray-200">
                                
                                {{-- Tanggal Mulai --}}
                                <div {{ !$this->is_active ? 'style=display:none' : '' }}>
                                    <label for="start_date" class="block mb-2.5 text-sm font-medium text-heading">Tanggal Mulai</label>
                                    <input type="date" id="start_date" wire:model.live="start_date" class="bg-white border border-default-medium text-heading text-sm rounded-md focus:ring-brand focus:border-brand block w-full px-3 py-2.5 shadow-xs">
                                    <p class="mt-1.5 text-xs text-brand-strong font-medium">{{ $this->getFormattedDate($start_date) }}</p>
                                    @error('start_date') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                                </div>
                                
                                {{-- Tanggal Berakhir (Menyesuaikan dengan tipe pendaftaran) --}}
                                @if(in_array($registration_type, ['membership', 'visit']))
                                <div class="{{ !$this->is_active ? 'hidden' : '' }}">
                                    <label for="membership_end_date" class="block mb-2.5 text-sm font-medium text-heading">Tanggal Berakhir Gym</label>
                                    <input type="date" id="membership_end_date" wire:model.live="membership_end_date" class="bg-white border border-default-medium text-heading text-sm rounded-md focus:ring-brand focus:border-brand block w-full px-3 py-2.5 shadow-xs">
                                    <p class="mt-1.5 text-xs text-brand-strong font-medium">{{ $this->getFormattedDate($membership_end_date) }}</p>
                                    @error('membership_end_date') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                                </div>
                                @endif

                                @if(in_array($registration_type, ['pt']))
                                <div class="{{ !$this->is_active ? 'hidden' : '' }}">
                                    <label for="pt_end_date" class="block mb-2.5 text-sm font-medium text-heading">Berakhir Sesi PT</label>
                                    <input type="date" id="pt_end_date" wire:model.live="pt_end_date" class="bg-white border border-default-medium text-heading text-sm rounded-md focus:ring-brand focus:border-brand block w-full px-3 py-2.5 shadow-xs">
                                    <p class="mt-1.5 text-xs text-brand-strong font-medium">{{ $this->getFormattedDate($pt_end_date) }}</p>
                                    @error('pt_end_date') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                                </div>
                                @endif

                                {{-- Hitungan Durasi --}}
                                <div class="flex flex-col justify-start items-start {{ $registration_type === 'visit' ? 'md:col-span-2' : '' }} {{ !$this->is_active ? 'hidden' : '' }}">
                                    <span class="text-xs text-gray-500 mb-1">Total Durasi Program:</span>
                                    <span class="text-md font-bold text-brand-strong  px-3 py-2 rounded-md inline-block w-full border border-brand-medium">
                                        ⏱️ {{ $this->programDuration }}
                                    </span>
                                </div>

                            </div>
                        </div>

                        {{-- 3. FORM MEMBERSHIP GYM (TERMASUK VISIT) --}}
                        @if(in_array($registration_type, ['membership', 'bundle_pt_membership', 'visit']))
                        <div class="md:col-span-2 mt-2 p-4 bg-gray-50 rounded-md border border-gray-200">
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
                            </div>
                        </div>
                        @endif

                        {{-- 4. FORM PERSONAL TRAINER --}}
                        @if(in_array($registration_type, ['pt', 'bundle_pt_membership']))
                        <div class="md:col-span-2 mt-2 p-4 bg-blue-50 rounded-md border border-blue-100">
                            <h6 class="text-sm font-semibold text-blue-800 mb-4 border-b border-blue-200 pb-2">Detail Personal Trainer (PT)</h6>
                            <div class="grid gap-6 md:grid-cols-2">
                                <div class="md:col-span-2">
                                    <label for="pt_package_id" class="block mb-2.5 text-sm font-medium text-heading">Pilih Paket Layanan PT</label>
                                    <select id="pt_package_id" wire:model.live="pt_package_id" class="bg-white border border-blue-300 text-blue-900 text-sm rounded-md focus:ring-blue-500 focus:border-blue-500 block w-full px-3 py-2.5 shadow-xs">
                                        <option value="">-- Pilih Paket PT --</option>
                                        @foreach($this->ptPackages as $package)
                                            <option value="{{ $package->id }}">
                                                {{ $package->name }} [{{ $package->pt_sessions }} Sesi] (Rp {{ number_format($package->price, 0, ',', '.') }})
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('pt_package_id') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                                </div>

                                <div class="md:col-span-2">
                                    <label for="pt_id" class="block mb-2.5 text-sm font-medium text-heading">Pilih Personal Trainer</label>
                                    <select id="pt_id" wire:model.live="pt_id" class="bg-white border border-default-medium text-heading text-sm rounded-md focus:ring-brand focus:border-brand block w-full px-3 py-2.5 shadow-xs">
                                        <option value="">-- Pilih Trainer --</option>
                                        @foreach($this->trainers as $trainer)
                                            <option value="{{ $trainer->id }}">{{ $trainer->name }}</option>
                                        @endforeach
                                    </select>
                                    @error('pt_id') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                                </div>
                            </div>
                        </div>
                        @endif

                        {{-- 5. FORM PETUGAS INTERNAL --}}
                        <div class="md:col-span-2 mt-2 p-4 bg-gray-50 rounded-md border border-gray-200">
                            <h6 class="text-sm font-semibold text-heading mb-4 border-b border-gray-200 pb-2">Detail Petugas Internal</h6>
                            <div class="grid gap-6 md:grid-cols-2">
                                
                                {{-- Dropdown Admin / Kasir --}}
                                <div class="col-span-2">
                                    <label for="admin_id" class="block mb-2.5 text-sm font-medium text-heading">Shift</label>
                                    <select id="admin_id" wire:model="admin_id" class="bg-white border border-default-medium text-heading text-sm rounded-md focus:ring-brand focus:border-brand block w-full px-3 py-2.5 shadow-xs">
                                        <option value="">-- Pilih Shift --</option>
                                        @foreach($this->adminUsers as $admin)
                                            <option value="{{ $admin->id }}">{{ $admin->name }} ({{ $admin->shift }})</option>
                                        @endforeach
                                    </select>
                                    @error('admin_id') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                                </div>

                                <div>
                                    <label for="follow_up_id" class="block mb-2.5 text-sm font-medium text-heading">Admin Follow Up</label>
                                    <select id="follow_up_id" wire:model="follow_up_id" class="bg-white border border-default-medium text-heading text-sm rounded-md focus:ring-brand focus:border-brand block w-full px-3 py-2.5 shadow-xs">
                                        <option value="">-- Pilih Staff --</option>
                                        @foreach($this->followUpUsers as $staff)
                                            <option value="{{ $staff->id }}">{{ $staff->name }}</option>
                                        @endforeach
                                    </select>
                                    @error('follow_up_id') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                                </div>

                                <div>
                                    <label for="follow_up_id_two" class="block mb-2.5 text-sm font-medium text-heading">Sales Follow Up</label>
                                    <select id="follow_up_id_two" wire:model="follow_up_id_two" class="bg-white border border-default-medium text-heading text-sm rounded-md focus:ring-brand focus:border-brand block w-full px-3 py-2.5 shadow-xs">
                                        <option value="">-- Pilih Staff --</option>
                                        @foreach($this->followUpUsers as $staff)
                                            <option value="{{ $staff->id }}">{{ $staff->name }}</option>
                                        @endforeach
                                    </select>
                                    @error('follow_up_id_two') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                                </div>

                            </div>
                        </div>

                    @else
                        {{-- State Kosong --}}
                        <div class="md:col-span-2 text-center py-8 text-gray-400 border border-dashed border-gray-300 rounded-md mt-4">
                            Silakan pilih jenis pendaftaran di atas untuk menampilkan form detail.
                        </div>
                    @endif

                </div>
            </form>
        </div>

        {{-- KOLOM KANAN: Ringkasan & Pembayaran Kasir --}}
        <div>
            <div class="bg-neutral-primary-soft p-6 shadow-xs rounded-md border border-default sticky top-6">
                <h6 class="text-lg font-semibold text-heading mb-4 pb-4 border-b border-default-medium">Ringkasan & Kasir</h6>
                
                <div class="space-y-3 mb-6 text-sm text-body">
                    @if(!$registration_type)
                        <div class="flex justify-between text-gray-400">
                            <span>Belum ada layanan dipilih</span>
                            <span>Rp 0</span>
                        </div>
                    @endif

                    @if(in_array($registration_type, ['membership', 'bundle_pt_membership', 'visit']) && $gym_package_id)
                        @php 
                            $gymPkg = App\Models\GymPackage::find($gym_package_id);
                            $multiplier = ($registration_type === 'visit') ? $selectedUsers->count() : 1; 
                        @endphp
                        @if($gymPkg)
                            <div class="flex justify-between">
                                <span>{{ $registration_type === 'visit' ? 'Paket Visit' : 'Paket Gym' }}</span>
                                <span class="font-medium text-heading">Rp {{ number_format($gymPkg->price * ($registration_type === 'visit' ? $selectedUsers->count() : 1), 0, ',', '.') }}</span>
                            </div>
                            @if($gymPkg->discount > 0)
                                <div class="flex justify-between text-red-500 text-xs mt-1">
                                    <span>Diskon Paket</span>
                                    <span>- Rp {{ number_format($gymPkg->discount * $multiplier, 0, ',', '.') }}</span>
                                </div>
                            @endif
                        @endif
                    @endif

                    @if(in_array($registration_type, ['pt', 'bundle_pt_membership']) && $pt_package_id)
                        <div class="border-t border-default-medium my-2"></div>
                        @php $ptPkg = App\Models\GymPackage::find($pt_package_id); @endphp
                        @if($ptPkg)
                            <div class="flex justify-between">
                                <span>Paket PT ({{ $ptPkg->pt_sessions }} Sesi)</span>
                                <span class="font-medium text-heading">Rp {{ number_format($ptPkg->price, 0, ',', '.') }}</span>
                            </div>
                            @if($ptPkg->discount > 0)
                                <div class="flex justify-between text-red-500 text-xs mt-1">
                                    <span>Diskon Paket PT</span>
                                    <span>- Rp {{ number_format($ptPkg->discount, 0, ',', '.') }}</span>
                                </div>
                            @endif
                        @endif
                    @endif
                    @if((int)$manual_discount > 0)
                        <div class="border-t border-default-medium my-2"></div>
                        <div class="flex justify-between text-red-500 font-medium text-sm">
                            <span>Diskon Tambahan (Manual)</span>
                            <span>- Rp {{ number_format((int)$manual_discount, 0, ',', '.') }}</span>
                        </div>
                    @endif
                </div>

                <div class="border-y border-default-medium py-3 mb-4 flex justify-between items-center bg-gray-50 px-3 rounded">
                    <span class="text-sm font-semibold text-heading">Total Tagihan:</span>
                    <span class="text-2xl font-bold text-brand-strong">Rp {{ number_format($price_paid, 0, ',', '.') }}</span>
                </div>
                {{-- FORM INPUT DISKON MANUAL --}}
                @if($registration_type && $price_paid > 0)
                <div class="mb-4">
                    <label class="block mb-1 text-sm font-medium text-heading">Diskon Tambahan</label>
                    <div x-data="{ 
                        discount: $wire.entangle('manual_discount').live, 
                        formatted: '',
                        init() {
                            this.formatValue(this.discount);
                            $watch('discount', value => {
                                this.formatValue(value);
                            });
                        },
                        formatValue(value) {
                            if (!value) {
                                this.formatted = '';
                                return;
                            }
                            let raw = value.toString().replace(/\D/g, '');
                            this.formatted = new Intl.NumberFormat('id-ID').format(raw);
                        },
                        updateValue(event) {
                            let raw = event.target.value.replace(/\D/g, '');
                            this.discount = raw; 
                            this.formatValue(raw);
                        }
                    }">
                        <input type="text" 
                            x-model="formatted" 
                            @input="updateValue($event)"
                            class="bg-white border border-default-medium text-heading text-lg font-bold rounded-md focus:ring-brand focus:border-brand block w-full px-3 py-2 shadow-xs text-red-600" 
                            placeholder="Diskon (Jika Ada)">
                    </div>
                    @error('manual_discount') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
                @endif

                {{-- FORM KASIR (BAYAR) --}}
                @if($registration_type && $price_paid > 0)
                <div class="space-y-4 mb-6">
                    
                    {{-- Pilihan Nyicil / Lunas --}}
                    @if($registration_type !== 'visit')
                    <div>
                        <label class="block mb-2 text-sm font-medium text-heading">Tipe Pembayaran</label>
                        <div class="flex gap-4">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" wire:model.live="payment_type" value="paid" class="text-brand focus:ring-brand w-4 h-4">
                                <span class="text-sm font-medium">Lunas</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" wire:model.live="payment_type" value="partial" class="text-brand focus:ring-brand w-4 h-4">
                                <span class="text-sm font-medium">Nyicil (DP)</span>
                            </label>
                        </div>
                    </div>
                    @endif

                    {{-- Nominal Dibayar Sekarang --}}
                    <div>
                        <label class="block mb-1 text-sm font-medium text-heading">Uang Diterima (Rp)</label>
                        
                        {{-- Menggunakan Alpine.js dengan entangle.live agar real-time --}}
                        <div x-data="{ 
                            amount: $wire.entangle('amount_paid').live, 
                            formatted: '',
                            init() {
                                this.formatValue(this.amount);
                                
                                // Pantau perubahan jika kasir klik tombol 'Lunas'
                                $watch('amount', value => {
                                    this.formatValue(value);
                                });
                            },
                            formatValue(value) {
                                if (!value) {
                                    this.formatted = '';
                                    return;
                                }
                                let raw = value.toString().replace(/\D/g, '');
                                this.formatted = new Intl.NumberFormat('id-ID').format(raw);
                            },
                            updateValue(event) {
                                let raw = event.target.value.replace(/\D/g, '');
                                this.amount = raw; // Ini otomatis trigger network request ke Livewire!
                                this.formatValue(raw);
                            }
                        }">
                            <input type="text" 
                                x-model="formatted" 
                                @input="updateValue($event)"
                                class="bg-white border border-default-medium text-heading text-lg font-bold rounded-md focus:ring-brand focus:border-brand block w-full px-3 py-2 shadow-xs {{ $payment_type === 'paid' ? 'bg-gray-100 text-gray-500 cursor-not-allowed' : 'text-green-600' }}" 
                                {{ $payment_type === 'paid' ? 'readonly' : '' }}
                                placeholder="Contoh: 150.000" required>
                        </div>

                        @error('amount_paid') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                        
                        @if($payment_type === 'partial' && $amount_paid > 0)
                            <div class="bg-orange-50 text-orange-700 text-xs px-3 py-2 rounded mt-2 font-medium border border-orange-200">
                                Sisa Tagihan (Utang): Rp {{ number_format($price_paid - $amount_paid, 0, ',', '.') }}
                            </div>
                        @endif
                    </div>

                    {{-- Metode Bayar --}}
                    <div>
                        <label class="block mb-1 text-sm font-medium text-heading">Metode Pembayaran</label>
                        <select wire:model="payment_method" class="bg-white border border-default-medium text-heading text-sm rounded-md focus:ring-brand focus:border-brand block w-full px-3 py-2 shadow-xs">
                            <option value="cash">💵 Cash / Tunai</option>
                            <option value="transfer">🏦 Transfer Bank</option>
                            <option value="qris">📱 QRIS</option>
                            <option value="debit">💳 Debit</option>
                        </select>
                        @error('payment_method') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block mb-1 text-sm font-medium text-heading">Tanggal Pembayaran</label>
                        <input type="date" wire:model="payment_date" class="bg-white border border-default-medium text-heading text-sm rounded-md focus:ring-brand focus:border-brand block w-full px-3 py-2 shadow-xs">
                        <p class="mt-1.5 text-xs text-brand-strong font-medium">{{ $this->getFormattedDate($payment_date) }}</p>
                        @error('payment_date') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block mb-1 text-sm font-medium text-heading">Paket Member</label>
                        <textarea wire:model="package_name" rows="2" class="bg-white border border-default-medium text-heading text-sm rounded-md focus:ring-brand focus:border-brand block w-full px-3 py-2 shadow-xs placeholder-gray-400" placeholder="Contoh: 1 BULAN, 6 + 2 BULAN, PT 20 SESI"></textarea>
                        @error('package_name') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block mb-1 text-sm font-medium text-heading">Status</label>
                        <textarea wire:model="transaction_type" rows="2" class="bg-white border border-default-medium text-heading text-sm rounded-md focus:ring-brand focus:border-brand block w-full px-3 py-2 shadow-xs placeholder-gray-400" placeholder="Contoh: NEW MEMBER, NEW PT 20 SESI"></textarea>
                        @error('transaction_type') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block mb-1 text-sm font-medium text-heading">Catatan</label>
                        <textarea wire:model="notes" rows="2" class="bg-white border border-default-medium text-heading text-sm rounded-md focus:ring-brand focus:border-brand block w-full px-3 py-2 shadow-xs placeholder-gray-400" placeholder="Contoh: NEW MEMBER, NEW PT 20 SESI"></textarea>
                        @error('notes') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                </div>
                @endif

                <button 
                    type="button" 
                    wire:click="save"
                    class="w-full text-center text-white bg-brand hover:bg-brand-strong focus:ring-4 focus:ring-brand-medium font-medium rounded-md text-sm px-4 py-3 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                    wire:loading.attr="disabled"
                >
                    <span wire:loading.remove>Konfirmasi & Simpan Transaksi</span>
                    <span wire:loading>Memproses...</span>
                </button>
            </div>
        </div>

    </div>
</div>