<?php

namespace App\Livewire\Admin;

use App\Models\GymPackage;
use App\Models\Membership as MembershipModel;
use App\Models\MembershipTransaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::admin')] class extends Component
{
    public $membershipId;

    public $membership;

    public $selectedUsers;

    public $mainUser;

    public $registration_type = '';

    public $gym_package_id = '';

    public $membership_end_date = '';

    public $pt_package_id = '';

    public $pt_id = '';

    public $pt_end_date = '';

    public $start_date = '';

    public $base_price = 0;

    public $manual_discount = 0;

    public $discount_applied = 0;

    public $price_paid = 0;

    public $normal_price_ref = 0;

    public $net_price_ref = 0;

    public $unrecommended_price_ref = 0;

    public $calculated_total_sessions = 0;

    public $total_sessions = 0;

    public $remaining_sessions = 0;

    public $sesi_ditambahkan = 0;

    public $sesi_hangus = 0;

    public $payment_type = 'paid';

    public $amount_paid = 0;

    public $payment_method = 'cash';

    public $payment_date = '';

    public $transaction_type = '';

    public $package_name = '';

    public $notes = '';

    public $admin_id = '';

    public $follow_up_id = '';

    public $follow_up_id_two = '';

    public $is_active = true;

    public $originalStartDate = '';

    public $originalMembershipEndDate = '';

    public $originalPtEndDate = '';

    public array $transactions = [];

    public $showPaymentWarning = false;

    public $paymentWarningMessage = '';

    public $redirectTo = '';

    public $redirectId = '';

    public function mount($id)
    {
        $this->membershipId = $id;
        $this->redirectTo = request()->query('redirect_to', '');
        $this->redirectId = request()->query('redirect_id', '');
        $this->membership = MembershipModel::with(['user', 'members', 'transactions'])->findOrFail($id);

        $this->selectedUsers = $this->membership->members;
        $this->mainUser = $this->membership->user;

        $this->registration_type = $this->membership->type;
        $this->gym_package_id = $this->membership->gym_package_id;
        $this->pt_package_id = $this->membership->pt_package_id;
        $this->pt_id = $this->membership->pt_id;
        $this->originalStartDate = $this->membership->start_date?->format('Y-m-d') ?? '';
        $this->originalMembershipEndDate = $this->membership->membership_end_date?->format('Y-m-d') ?? '';
        $this->originalPtEndDate = $this->membership->pt_end_date?->format('Y-m-d') ?? '';

        $this->start_date = $this->originalStartDate;
        $this->membership_end_date = $this->originalMembershipEndDate;
        $this->pt_end_date = $this->originalPtEndDate;

        $this->base_price = $this->membership->base_price;
        $this->discount_applied = $this->membership->discount_applied;
        $this->price_paid = $this->membership->price_paid;
        $this->normal_price_ref = $this->membership->normal_price ?? 0;
        $this->net_price_ref = $this->membership->net_price ?? 0;
        $this->unrecommended_price_ref = $this->membership->unrecommended_price ?? 0;

        // Hitung diskon manual dari data lama (total diskon - diskon paket)
        $gymDiscount = 0;
        $ptDiscount = 0;
        $jumlahUser = $this->selectedUsers->count();

        if (in_array($this->registration_type, ['membership', 'bundle_pt_membership', 'visit']) && $this->gym_package_id) {
            $pkg = GymPackage::find($this->gym_package_id);
            if ($pkg) {
                $multiplier = ($this->registration_type === 'visit') ? $jumlahUser : 1;
                $gymDiscount = ($pkg->discount ?? 0) * $multiplier;
            }
        }

        if (in_array($this->registration_type, ['pt', 'bundle_pt_membership']) && $this->pt_package_id) {
            $pkg = GymPackage::find($this->pt_package_id);
            if ($pkg) {
                $ptDiscount = $pkg->discount ?? 0;
            }
        }

        $this->manual_discount = max(0, $this->membership->discount_applied - ($gymDiscount + $ptDiscount));
        $this->calculated_total_sessions = $this->membership->total_sessions ?? 0;
        $this->total_sessions = $this->membership->total_sessions ?? 0;
        $this->remaining_sessions = $this->membership->remaining_sessions ?? 0;
        $this->sesi_ditambahkan = $this->membership->sesi_ditambahkan ?? 0;
        $this->sesi_hangus = $this->membership->sesi_hangus ?? 0;

        $this->payment_type = $this->membership->payment_status;
        $this->amount_paid = $this->membership->total_paid;
        $this->is_active = $this->membership->is_active;

        $latestTxn = $this->membership->transactions->sortByDesc('payment_date')->first();

        if ($latestTxn) {
            $this->payment_method = $latestTxn->payment_method;
            $this->payment_date = $latestTxn->payment_date?->format('Y-m-d') ?? now()->format('Y-m-d');
            $this->transaction_type = $latestTxn->transaction_type;
            $this->package_name = $latestTxn->package_name;
            $this->notes = $latestTxn->notes ?? '';
            $this->admin_id = $latestTxn->admin_id;
            $this->follow_up_id = $latestTxn->follow_up_id;
            $this->follow_up_id_two = $latestTxn->follow_up_id_two;
        } else {
            $this->payment_method = 'cash';
            $this->payment_date = now()->format('Y-m-d');
            $this->transaction_type = '';
            $this->package_name = '';
            $this->notes = $this->membership->notes ?? '';
            $this->admin_id = $this->membership->admin_id;
            $this->follow_up_id = $this->membership->follow_up_id;
            $this->follow_up_id_two = $this->membership->follow_up_id_two;
        }

        foreach ($this->membership->transactions as $txn) {
            $this->transactions[] = [
                'id' => $txn->id,
                'invoice_number' => $txn->invoice_number,
                'transaction_type' => $txn->transaction_type,
                'package_name' => $txn->package_name,
                'amount' => $txn->amount,
                'payment_method' => $txn->payment_method,
                'payment_date' => $txn->payment_date ? Carbon::parse($txn->payment_date)->format('Y-m-d') : '',
                'start_date' => $txn->start_date ? Carbon::parse($txn->start_date)->format('Y-m-d') : '',
                'end_date' => $txn->end_date ? Carbon::parse($txn->end_date)->format('Y-m-d') : '',
                'notes' => $txn->notes ?? '',
                'admin_id' => $txn->admin_id,
                'follow_up_id' => $txn->follow_up_id,
                'follow_up_id_two' => $txn->follow_up_id_two,
            ];
        }
    }

    #[Computed]
    public function adminUsers()
    {
        return User::whereIn('role', ['kasir_gym'])->where('is_active', true)->get();
    }

    #[Computed]
    public function followUpUsers()
    {
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

    #[Computed]
    public function priceCategory()
    {
        $pricePaid = (float) $this->price_paid;
        $normalPrice = (float) $this->normal_price_ref;
        $netPrice = (float) $this->net_price_ref;
        $basePrice = (float) $this->base_price;

        if (($normalPrice > 0 && $pricePaid >= $normalPrice) || ($basePrice > 0 && $pricePaid >= $basePrice)) {
            return ['label' => 'Harga Normal', 'class' => 'bg-blue-100 text-blue-800 border-blue-200'];
        }

        if ($netPrice > 0 && $pricePaid >= $netPrice) {
            return ['label' => 'Harga Net', 'class' => 'bg-emerald-100 text-emerald-800 border-emerald-200'];
        }

        if ($pricePaid > 0) {
            return ['label' => 'Harga Tidak Disarankan', 'class' => 'bg-red-100 text-red-800 border-red-200'];
        }

        return null;
    }

    #[Computed]
    public function programDuration()
    {
        if (! $this->start_date) {
            return '-';
        }

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

            $totalDays = $start->diffInDays($end) + 1;
            $months = floor($totalDays / 30);
            $days = $totalDays % 30;

            $parts = [];
            if ($months > 0) {
                $parts[] = $months.' Bulan';
            }
            if ($days > 0) {
                $parts[] = $days.' Hari';
            }

            if (empty($parts)) {
                return 'Berakhir hari ini';
            }

            return implode(' ', $parts);
        }

        return '-';
    }

    public function updated($property)
    {
        if ($property === 'is_active') {
            if (! $this->is_active) {
                $this->start_date = '';
                $this->membership_end_date = '';
                $this->pt_end_date = '';
            } else {
                $this->start_date = $this->originalStartDate;
                $this->membership_end_date = $this->originalMembershipEndDate;
                $this->pt_end_date = $this->originalPtEndDate;
            }
        }

        if ($property === 'registration_type') {
            $this->pt_package_id = '';
            $this->pt_id = '';
            $this->gym_package_id = '';

            if ($this->registration_type === 'visit') {
                $this->membership_end_date = $this->start_date;
                $this->payment_type = 'paid';
            }
        }

        if (in_array($property, ['registration_type', 'gym_package_id', 'pt_package_id', 'manual_discount'])) {
            $this->calculateTotal();
        }

        if (str_starts_with($property, 'transactions.')) {
            $this->calculateTotalPaid();
        }

        if ($property === 'start_date' && $this->start_date) {
            if ($this->registration_type === 'visit') {
                $this->membership_end_date = $this->start_date;
            } else {
                $this->membership_end_date = Carbon::parse($this->start_date)->addDays(29)->format('Y-m-d');
            }
            $this->pt_end_date = Carbon::parse($this->start_date)->addDays(29)->format('Y-m-d');
        }

        if ($property === 'payment_type') {
            if ($this->payment_type === 'paid') {
                $this->amount_paid = $this->price_paid;
            } else {
                $this->amount_paid = '';
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

        $pkt = null;
        $isGymActive = in_array($this->registration_type, ['membership', 'bundle_pt_membership', 'visit']);
        if ($isGymActive && $this->gym_package_id) {
            $package = GymPackage::find($this->gym_package_id);
            if ($package) {
                $multiplier = ($this->registration_type === 'visit') ? $jumlahUser : 1;
                $hargaGym = $package->price * $multiplier;
                $diskonGym = ($package->discount ?? 0) * $multiplier;
                $pkt = $package;
            }
        }

        $isPtActive = in_array($this->registration_type, ['pt', 'bundle_pt_membership']);
        if ($isPtActive && $this->pt_package_id) {
            $ptPackage = GymPackage::find($this->pt_package_id);
            if ($ptPackage) {
                $hargaPt = $ptPackage->price;
                $diskonPt = $ptPackage->discount ?? 0;
                $this->calculated_total_sessions = $ptPackage->pt_sessions;
                if (! $pkt) {
                    $pkt = $ptPackage;
                }
            }
        }

        $this->base_price = $hargaGym + $hargaPt;
        $diskonManualAngka = empty($this->manual_discount) ? 0 : (int) $this->manual_discount;
        $this->discount_applied = $diskonGym + $diskonPt + $diskonManualAngka;

        $this->price_paid = $this->base_price - $this->discount_applied;
        if ($this->price_paid < 0) {
            $this->price_paid = 0;
        }

        if ($pkt) {
            $this->normal_price_ref = $pkt->normal_price ?? 0;
            $this->net_price_ref = $pkt->net_price ?? 0;
            $this->unrecommended_price_ref = $pkt->unrecommended_price ?? 0;
        }

        $this->calculateTotalPaid();
    }

    public function calculateTotalPaid()
    {
        $this->amount_paid = collect($this->transactions)->sum(fn ($t) => (float) ($t['amount'] ?? 0));
        $this->payment_type = $this->amount_paid >= $this->price_paid ? 'paid' : 'partial';
    }

    public function cancelPaymentWarning(): void
    {
        $this->showPaymentWarning = false;
        $this->paymentWarningMessage = '';
    }

    public function getFormattedDate($date)
    {
        if (! $date) {
            return '';
        }
        Carbon::setLocale('id');

        return Carbon::parse($date)->translatedFormat('l, d F Y');
    }

    public function save()
    {
        if (! $this->registration_type) {
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
            'manual_discount' => 'nullable|numeric|min:0|max:'.$this->base_price,
        ];

        if ($this->payment_type === 'partial') {
            $rules['amount_paid'] = 'required|numeric|min:1|max:'.($this->price_paid - 1);
        }

        if (in_array($this->registration_type, ['membership', 'bundle_pt_membership', 'visit'])) {
            $rules['gym_package_id'] = 'required|exists:gym_packages,id';
            $rules['membership_end_date'] = $this->is_active ? 'required|date|after_or_equal:start_date' : 'nullable|date';
        }

        if (in_array($this->registration_type, ['pt', 'bundle_pt_membership'])) {
            $rules['pt_package_id'] = 'required|exists:gym_packages,id';
            $rules['pt_id'] = 'nullable|exists:users,id';
            $rules['pt_end_date'] = $this->is_active ? 'required|date|after_or_equal:start_date' : 'nullable|date';
        }

        $this->validate($rules, [
            'amount_paid.max' => 'Nominal cicilan tidak boleh lebih atau sama dengan total tagihan.',
            'amount_paid.min' => 'Nominal cicilan harus lebih dari 0.',
            'manual_discount.max' => 'Diskon tidak boleh melebihi total harga paket.',
        ]);

        $this->calculateTotal();

        if (! $this->showPaymentWarning) {
            if ((float) $this->amount_paid < (float) $this->price_paid) {
                $this->paymentWarningMessage = 'Total terbayar (Rp ' . number_format($this->amount_paid, 0, ',', '.') . ') kurang dari harga final (Rp ' . number_format($this->price_paid, 0, ',', '.') . '). Data akan masuk ke status Cicilan (Partial).';
                $this->showPaymentWarning = true;

                return;
            }

            if ((float) $this->amount_paid > (float) $this->price_paid) {
                $this->paymentWarningMessage = 'Total terbayar (Rp ' . number_format($this->amount_paid, 0, ',', '.') . ') melebihi harga final (Rp ' . number_format($this->price_paid, 0, ',', '.') . ').';
                $this->showPaymentWarning = true;

                return;
            }
        }

        $this->showPaymentWarning = false;

        $pkt = null;
        if (in_array($this->registration_type, ['membership', 'bundle_pt_membership', 'visit']) && $this->gym_package_id) {
            $pkt = GymPackage::find($this->gym_package_id);
        } elseif ($this->registration_type === 'pt' && $this->pt_package_id) {
            $pkt = GymPackage::find($this->pt_package_id);
        }

        try {
            DB::beginTransaction();

            $this->membership->update([
                'type' => $this->registration_type,
                'gym_package_id' => in_array($this->registration_type, ['membership', 'bundle_pt_membership', 'visit']) ? $this->gym_package_id : null,
                'pt_package_id' => in_array($this->registration_type, ['pt', 'bundle_pt_membership']) ? $this->pt_package_id : null,
                'pt_id' => in_array($this->registration_type, ['pt', 'bundle_pt_membership']) ? ($this->pt_id ?: null) : null,
                'admin_id' => $this->admin_id,
                'follow_up_id' => $this->follow_up_id ?: null,
                'follow_up_id_two' => $this->follow_up_id_two ?: null,
                'base_price' => $this->base_price,
                'discount_applied' => $this->discount_applied,
                'normal_price' => $pkt?->normal_price,
                'net_price' => $pkt?->net_price,
                'unrecommended_price' => $pkt?->unrecommended_price,
                'price_paid' => $this->price_paid,
                'total_sessions' => in_array($this->registration_type, ['pt', 'bundle_pt_membership']) ? $this->total_sessions : null,
                'remaining_sessions' => in_array($this->registration_type, ['pt', 'bundle_pt_membership']) ? $this->remaining_sessions : null,
                'sesi_ditambahkan' => in_array($this->registration_type, ['pt', 'bundle_pt_membership']) ? $this->sesi_ditambahkan : null,
                'sesi_hangus' => in_array($this->registration_type, ['pt', 'bundle_pt_membership']) ? $this->sesi_hangus : null,
                'start_date' => $this->start_date ?: null,
                'membership_end_date' => in_array($this->registration_type, ['membership', 'bundle_pt_membership', 'visit']) ? ($this->membership_end_date ?: null) : null,
                'pt_end_date' => in_array($this->registration_type, ['pt', 'bundle_pt_membership']) ? ($this->pt_end_date ?: null) : null,
                'is_active' => $this->is_active,
                'notes' => $this->notes,
            ]);

            $this->membership->members()->sync($this->selectedUsers->pluck('id')->toArray());

            foreach ($this->transactions as $txnData) {
                $txn = MembershipTransaction::find($txnData['id']);
                if ($txn) {
                    $txn->update([
                        'admin_id' => $this->admin_id,
                        'follow_up_id' => $this->follow_up_id ?: null,
                        'follow_up_id_two' => $this->follow_up_id_two ?: null,
                        'transaction_type' => $txnData['transaction_type'],
                        'package_name' => $this->package_name,
                        'amount' => $txnData['amount'],
                        'payment_method' => $txnData['payment_method'],
                        'payment_date' => $txnData['payment_date'],
                        'start_date' => $this->start_date ?: null,
                        'end_date' => in_array($this->registration_type, ['pt']) ? ($this->pt_end_date ?: null) : ($this->membership_end_date ?: null),
                        'notes' => $txnData['notes'],
                    ]);
                }
            }

            $calculatedTotalPaid = collect($this->transactions)->sum(fn ($t) => (float) $t['amount']);
            $paymentStatus = $calculatedTotalPaid >= $this->price_paid ? 'paid' : 'partial';

            $this->membership->update([
                'total_paid' => $calculatedTotalPaid,
                'payment_status' => $paymentStatus,
                'status' => $paymentStatus === 'paid' ? 'active' : 'pending',
            ]);

            DB::commit();

            session()->flash('success', 'Data membership dan transaksi berhasil diperbarui.');

            if ($this->redirectTo && $this->redirectId) {
                return $this->redirectRoute($this->redirectTo, ['user' => $this->redirectId], navigate: true);
            }

            return $this->redirectRoute('admin.membership.index', navigate: true);

        } catch (\Exception $e) {
            DB::rollBack();
            session()->flash('error', 'Terjadi kesalahan sistem: '.$e->getMessage());

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
    @if (session()->has('success'))
        <div class="mb-4 p-4 text-sm text-green-800 rounded-lg bg-green-50 border border-green-200">
            {{ session('success') }}
        </div>
    @endif

    @if($showPaymentWarning)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm" wire:click.self="cancelPaymentWarning">
            <div class="bg-white rounded-lg shadow-xl border border-default-medium w-full max-w-md mx-4 p-6">
                <div class="flex items-center gap-3 mb-4">
                    <div class="flex-shrink-0 w-10 h-10 rounded-full bg-orange-100 flex items-center justify-center">
                        <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                    </div>
                    <h3 class="text-lg font-semibold text-heading">Perhatian</h3>
                </div>
                <p class="text-sm text-body mb-6">{{ $paymentWarningMessage }}</p>
                <div class="flex gap-3">
                    <button type="button" wire:click="cancelPaymentWarning" class="flex-1 text-center text-body bg-white border border-default-medium hover:bg-gray-50 focus:ring-4 focus:ring-gray-100 font-medium rounded-md text-sm px-4 py-2.5 transition-colors">
                        Batal
                    </button>
                    <button type="button" wire:click="save" class="flex-1 text-center text-white bg-brand hover:bg-brand-strong focus:ring-4 focus:ring-brand-medium font-medium rounded-md text-sm px-4 py-2.5 transition-colors">
                        Lanjutkan Simpan
                    </button>
                </div>
            </div>
        </div>
    @endif

    <div class="mb-6 flex items-end gap-2">
        <a href="{{ route('admin.membership.index') }}" wire:navigate class="p-2 bg-white border border-default rounded-md hover:bg-gray-50 text-gray-600 transition-colors">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
        </a>
        <div>
            <h5 class="text-xl font-semibold text-heading mb-2">Edit Membership & Transaksi</h5>
            <p class="text-body text-sm">Ubah data membership dan transaksi kasir.</p>
        </div>
    </div>

    <div class="space-y-6">

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
                        <div class="md:col-span-2">
                            <label class="block mb-2 text-sm font-medium text-heading">Status Membership</label>
                            <div class="flex gap-4">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="radio" wire:model.live="is_active" value="1" class="text-brand focus:ring-brand w-4 h-4">
                                    <span class="text-sm font-medium">Aktif Sekarang</span>
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="radio" wire:model.live="is_active" value="0" class="text-red-600 focus:ring-red-500 w-4 h-4">
                                    <span class="text-sm font-medium text-red-600">Tidak Aktif (Pending)</span>
                                </label>
                            </div>
                        </div>

                        <div class="md:col-span-2">
                            <h6 class="text-sm font-semibold text-heading mb-3">Durasi Program & Tanggal Aktif</h6>
                            <div class="grid gap-4 md:grid-cols-3 bg-gray-50 p-4 rounded border border-gray-200">

                                <div>
                                    <label for="start_date" class="block mb-2.5 text-sm font-medium text-heading">Tanggal Mulai</label>
                                    <input type="date" id="start_date" wire:model.live="start_date" @disabled(! $is_active) class="border border-default-medium text-heading text-sm rounded-md focus:ring-brand focus:border-brand block w-full px-3 py-2.5 shadow-xs @if(! $is_active) bg-gray-100 text-gray-500 cursor-not-allowed @else bg-white @endif">
                                    <p class="mt-1.5 text-xs text-brand-strong font-medium">{{ $this->getFormattedDate($start_date) }}</p>
                                    @error('start_date') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                                </div>

                                @if(in_array($registration_type, ['membership', 'visit']))
                                <div class="{{ $registration_type === 'visit' ? 'hidden' : '' }}">
                                    <label for="membership_end_date" class="block mb-2.5 text-sm font-medium text-heading">Tanggal Berakhir Gym</label>
                                    <input type="date" id="membership_end_date" wire:model.live="membership_end_date" @disabled(! $is_active) class="border border-default-medium text-heading text-sm rounded-md focus:ring-brand focus:border-brand block w-full px-3 py-2.5 shadow-xs @if(! $is_active) bg-gray-100 text-gray-500 cursor-not-allowed @else bg-white @endif">
                                    <p class="mt-1.5 text-xs text-brand-strong font-medium">{{ $this->getFormattedDate($membership_end_date) }}</p>
                                    @error('membership_end_date') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                                </div>
                                @endif

                                @if(in_array($registration_type, ['pt']))
                                <div>
                                    <label for="pt_end_date" class="block mb-2.5 text-sm font-medium text-heading">Berakhir Sesi PT</label>
                                    <input type="date" id="pt_end_date" wire:model.live="pt_end_date" @disabled(! $is_active) class="border border-default-medium text-heading text-sm rounded-md focus:ring-brand focus:border-brand block w-full px-3 py-2.5 shadow-xs @if(! $is_active) bg-gray-100 text-gray-500 cursor-not-allowed @else bg-white @endif">
                                    <p class="mt-1.5 text-xs text-brand-strong font-medium">{{ $this->getFormattedDate($pt_end_date) }}</p>
                                    @error('pt_end_date') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                                </div>
                                @endif

                                <div class="flex flex-col justify-start items-start {{ $registration_type === 'visit' ? 'md:col-span-2' : '' }}">
                                    <span class="text-xs text-gray-500 mb-1">Total Durasi Program:</span>
                                    <span class="text-md font-bold text-brand-strong  px-3 py-2 rounded-md inline-block w-full border border-brand-medium">
                                        ⏱️ {{ $this->programDuration }}
                                    </span>
                                </div>

                            </div>
                        </div>

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

                        @if(in_array($registration_type, ['pt', 'bundle_pt_membership']))
                        <div class="md:col-span-2 mt-2 p-4 bg-blue-50 rounded-md border border-blue-100">
                            <h6 class="text-sm font-semibold text-blue-800 mb-4 border-b border-blue-200 pb-2">Detail Personal Trainer (PT)</h6>
                            <div class="grid gap-6 md:grid-cols-2">
                                <div class="md:col-span-1">
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

                                <div class="md:col-span-1 grid grid-cols-2 gap-4">
                                    <div>
                                        <label for="total_sessions" class="block mb-2 text-sm font-medium text-heading">Sesi Awal</label>
                                        <input type="number" id="total_sessions" wire:model="total_sessions" class="bg-white border border-default-medium text-heading text-sm rounded-md focus:ring-brand focus:border-brand block w-full px-3 py-2.5 shadow-xs">
                                        @error('total_sessions') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                                    </div>
                                    <div>
                                        <label for="remaining_sessions" class="block mb-2 text-sm font-medium text-heading">Sisa Sesi</label>
                                        <input type="number" id="remaining_sessions" wire:model="remaining_sessions" class="bg-white border border-default-medium text-heading text-sm rounded-md focus:ring-brand focus:border-brand block w-full px-3 py-2.5 shadow-xs">
                                        @error('remaining_sessions') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                                    </div>
                                    <div>
                                        <label for="sesi_ditambahkan" class="block mb-2 text-sm font-medium text-heading">Sesi Ditambahkan</label>
                                        <input type="number" id="sesi_ditambahkan" wire:model="sesi_ditambahkan" class="bg-white border border-default-medium text-heading text-sm rounded-md focus:ring-brand focus:border-brand block w-full px-3 py-2.5 shadow-xs">
                                        @error('sesi_ditambahkan') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                                    </div>
                                    <div>
                                        <label for="sesi_hangus" class="block mb-2 text-sm font-medium text-heading">Sesi Hangus</label>
                                        <input type="number" id="sesi_hangus" wire:model="sesi_hangus" class="bg-white border border-default-medium text-heading text-sm rounded-md focus:ring-brand focus:border-brand block w-full px-3 py-2.5 shadow-xs">
                                        @error('sesi_hangus') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                                    </div>
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

                        <div class="md:col-span-2 mt-2 p-4 bg-gray-50 rounded-md border border-gray-200">
                            <h6 class="text-sm font-semibold text-heading mb-4 border-b border-gray-200 pb-2">Detail Petugas Internal</h6>
                            <div class="grid gap-6 md:grid-cols-2">
                                
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

                        <div class="md:col-span-2 mt-2 p-4 bg-white shadow-xs rounded-md border border-default">
                            <h6 class="text-sm font-semibold text-heading mb-3 pb-2 border-b border-default-medium">Informasi Membership</h6>

                            <div class="grid gap-3 md:grid-cols-3 mb-4">
                                <div class="p-3 rounded-md border bg-blue-50 border-blue-200">
                                    <div class="text-xs font-medium text-blue-700 mb-1">Harga Normal</div>
                                    <div class="text-sm font-bold text-blue-900">
                                        Rp {{ number_format($this->normal_price_ref, 0, ',', '.') }}
                                    </div>
                                </div>

                                <div class="p-3 rounded-md border bg-emerald-50 border-emerald-200">
                                    <div class="text-xs font-medium text-emerald-700 mb-1">Harga Net</div>
                                    <div class="text-sm font-bold text-emerald-900">
                                        Rp {{ number_format($this->net_price_ref, 0, ',', '.') }}
                                    </div>
                                </div>

                                <div class="p-3 rounded-md border bg-red-50 border-red-200">
                                    <div class="text-xs font-medium text-red-700 mb-1">Harga Tidak Disarankan</div>
                                    <div class="text-sm font-bold text-red-900">
                                        Rp {{ number_format($this->unrecommended_price_ref, 0, ',', '.') }}
                                    </div>
                                </div>
                            </div>

                            <div class="grid gap-4 md:grid-cols-3">
                                <div>
                                    <label class="block mb-1 text-sm font-medium text-heading">Status Pembayaran</label>
                                    <input type="text" readonly value="{{ $payment_type === 'paid' ? 'Lunas' : 'Cicilan (Partial)' }}" class="bg-gray-100 border border-default-medium text-heading text-sm rounded-md block w-full px-3 py-2 shadow-xs cursor-not-allowed">
                                </div>

                                <div>
                                    <label class="block mb-1 text-sm font-medium text-heading">Diskon Manual</label>
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
                                            class="bg-white border border-default-medium text-heading text-sm rounded-md focus:ring-brand focus:border-brand block w-full px-3 py-2 shadow-xs"
                                            placeholder="Diskon (Jika Ada)">
                                    </div>
                                    @error('manual_discount') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                                </div>

                                <div>
                                    <label class="block mb-1 text-sm font-medium text-heading">Harga Final</label>
                                    <input type="text" readonly
                                        x-data="{ fmt(v) { return 'Rp ' + (v ? parseInt(v).toLocaleString('id-ID') : '0'); } }"
                                        x-effect="$el.value = fmt($wire.price_paid)"
                                        class="bg-gray-100 border border-default-medium text-heading text-sm rounded-md block w-full px-3 py-2 shadow-xs cursor-not-allowed"
                                    >
                                    @if($this->priceCategory)
                                        <div class="mt-1.5">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-semibold border {{ $this->priceCategory['class'] }}">
                                                {{ $this->priceCategory['label'] }}
                                            </span>
                                        </div>
                                    @endif
                                </div>

                                <div>
                                    <label class="block mb-1 text-sm font-medium text-heading">Total Terbayar</label>
                                    <input type="text" readonly
                                        x-data="{ fmt(v) { return 'Rp ' + (v ? parseInt(v).toLocaleString('id-ID') : '0'); } }"
                                        x-effect="$el.value = fmt($wire.amount_paid)"
                                        class="bg-gray-100 border border-default-medium text-heading text-sm rounded-md block w-full px-3 py-2 shadow-xs cursor-not-allowed"
                                    >
                                </div>
                            </div>
                        </div>

                        <div class="md:col-span-2 mt-2 p-4 bg-white shadow-xs rounded-md border border-default">
                            <h6 class="text-sm font-semibold text-heading mb-3 pb-2 border-b border-default-medium">Detail Transaksi</h6>
                            <div class="grid gap-4 md:grid-cols-3">
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
                                    <textarea wire:model="notes" rows="2" class="bg-white border border-default-medium text-heading text-sm rounded-md focus:ring-brand focus:border-brand block w-full px-3 py-2 shadow-xs placeholder-gray-400" placeholder="Catatan transaksi"></textarea>
                                    @error('notes') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                                </div>
                            </div>
                        </div>

                        <div class="md:col-span-2 mt-2">
                            @if(count($transactions) > 0)
                                <div class="bg-white p-6 shadow-xs rounded-md border border-default space-y-6">
                                    <h6 class="text-lg font-semibold text-heading mb-2 pb-2 border-b border-default-medium">Data Transaksi (MembershipTransaction)</h6>

                                    <div class="space-y-4">
                                        <h6 class="text-sm font-semibold text-heading mb-4">Edit Transaksi</h6>
                                        @foreach($transactions as $index => $txn)
                                            <div class="mb-6 p-4 bg-gray-50 rounded-md border border-gray-200 {{ $index > 0 ? 'mt-4' : '' }}">
                                                <div class="flex items-center justify-between mb-3">
                                                    <h6 class="text-sm font-semibold text-heading">
                                                        Transaksi #{{ $index + 1 }}
                                                        <span class="text-xs text-gray-500 font-normal ml-2">{{ $txn['invoice_number'] }}</span>
                                                    </h6>
                                                </div>
                                                
                                                <div class="grid gap-4 md:grid-cols-2">
                                                    <div>
                                                        <label class="block mb-1 text-sm font-medium text-heading">Status</label>
                                                        <input type="text" wire:model="transactions.{{ $index }}.transaction_type" class="bg-white border border-default-medium text-heading text-sm rounded-md focus:ring-brand focus:border-brand block w-full px-3 py-2 shadow-xs">
                                                    </div>

                                                    <div>
                                                        <label class="block mb-1 text-sm font-medium text-heading">Jumlah (Rp)</label>
                                                        <div x-data="{
                                                            amount: $wire.entangle('transactions.{{ $index }}.amount').live,
                                                            formatted: '',
                                                            init() {
                                                                this.formatValue(this.amount);
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
                                                                this.amount = raw;
                                                                this.formatValue(raw);
                                                            }
                                                        }">
                                                            <input type="text"
                                                                x-model="formatted"
                                                                @input="updateValue($event)"
                                                                class="bg-white border border-default-medium text-heading text-sm rounded-md focus:ring-brand focus:border-brand block w-full px-3 py-2 shadow-xs"
                                                                placeholder="Jumlah pembayaran">
                                                        </div>
                                                    </div>

                                                    <div>
                                                        <label class="block mb-1 text-sm font-medium text-heading">Metode Pembayaran</label>
                                                        <select wire:model="transactions.{{ $index }}.payment_method" class="bg-white border border-default-medium text-heading text-sm rounded-md focus:ring-brand focus:border-brand block w-full px-3 py-2 shadow-xs">
                                                            <option value="cash">💵 Cash / Tunai</option>
                                                            <option value="transfer">🏦 Transfer Bank</option>
                                                            <option value="qris">📱 QRIS</option>
                                                            <option value="debit">💳 Debit</option>
                                                        </select>
                                                    </div>

                                                    <div>
                                                        <label class="block mb-1 text-sm font-medium text-heading">Tanggal Pembayaran</label>
                                                        <input type="date" wire:model="transactions.{{ $index }}.payment_date" class="bg-white border border-default-medium text-heading text-sm rounded-md focus:ring-brand focus:border-brand block w-full px-3 py-2 shadow-xs">
                                                    </div>

                                                    <div class="md:col-span-2">
                                                        <label class="block mb-1 text-sm font-medium text-heading">Catatan</label>
                                                        <textarea wire:model="transactions.{{ $index }}.notes" rows="2" class="bg-white border border-default-medium text-heading text-sm rounded-md focus:ring-brand focus:border-brand block w-full px-3 py-2 shadow-xs placeholder-gray-400" placeholder="Catatan transaksi"></textarea>
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @else
                                <div class="bg-white p-6 shadow-xs rounded-md border border-default text-center py-8 text-gray-400">
                                    Belum ada data transaksi untuk membership ini.
                                </div>
                            @endif
                        </div>

                    @else
                        <div class="md:col-span-2 text-center py-8 text-gray-400 border border-dashed border-gray-300 rounded-md mt-4">
                            Silakan pilih jenis pendaftaran di atas untuk menampilkan form detail.
                        </div>
                    @endif

                </div>

                <div class="pt-4 border-t border-default-medium space-y-3">
                    <button
                        type="submit"
                        class="w-full text-center text-white bg-brand hover:bg-brand-strong focus:ring-4 focus:ring-brand-medium font-medium rounded-md text-sm px-4 py-3 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                        wire:loading.attr="disabled"
                    >
                        <span wire:loading.remove>Simpan Perubahan</span>
                        <span wire:loading>Memproses...</span>
                    </button>

                    @if($redirectTo && $redirectId)
                        <a href="{{ route($redirectTo, ['user' => $redirectId]) }}" wire:navigate class="block w-full text-center text-body bg-white border border-default-medium hover:bg-gray-50 focus:ring-4 focus:ring-gray-100 font-medium rounded-md text-sm px-4 py-3 transition-colors">
                            Batal
                        </a>
                    @else
                        <a href="{{ route('admin.membership.index') }}" wire:navigate class="block w-full text-center text-body bg-white border border-default-medium hover:bg-gray-50 focus:ring-4 focus:ring-gray-100 font-medium rounded-md text-sm px-4 py-3 transition-colors">
                            Batal
                        </a>
                    @endif
                </div>
            </form>
        </div>
    </div>
</div>
