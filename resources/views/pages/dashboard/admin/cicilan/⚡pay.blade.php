<?php

use App\Models\Membership;
use App\Models\MembershipTransaction;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

new #[Layout('layouts::admin')] class extends Component
{
    public Membership $membership;

    public $amount_paid;
    public $payment_method = 'cash';
    public $transaction_type = '';
    public $payment_date = '';


    public function mount(Membership $membership)
    {
        // Load relasi yang dibutuhkan
        $this->membership = $membership->load(['user', 'gymPackage', 'ptPackage', 'transactions']);
        
        // Pengecekan keamanan: Jika sudah lunas, lempar kembali ke halaman cicilan
        if ($this->membership->payment_status === 'paid') {
            session()->flash('error', 'Membership ini sudah lunas sepenuhnya.');
            return redirect()->route('admin.cicilan.index');
        }

        $this->payment_date = now()->format('Y-m-d');

        // Auto isi form dengan nominal sisa tagihan penuh
        // $this->amount_paid = $this->sisaTagihan;
    }

    public function getFormattedDate($date)
    {
        if (!$date) return '';
        Carbon::setLocale('id');
        return Carbon::parse($date)->translatedFormat('l, d F Y');
    }

    #[Computed]
    public function sisaTagihan()
    {
        return $this->membership->price_paid - $this->membership->total_paid;
    }

    public function save()
    {
        $this->validate([
            'amount_paid' => 'required|numeric|min:1|max:' . $this->sisaTagihan,
            'payment_method' => 'required|in:cash,transfer,qris,debit',
            'transaction_type' => 'required|string',
            'payment_date' => 'required|date',
        ], [
            'amount_paid.max' => 'Nominal bayar tidak boleh melebihi sisa tagihan saat ini.',
            'amount_paid.min' => 'Nominal bayar harus lebih dari 0.',
        ]);

        try {
            DB::beginTransaction();

            $newTotalPaid = $this->membership->total_paid + $this->amount_paid;
            
            // Cek apakah dengan bayaran ini statusnya jadi LUNAS
            $isLunas = $newTotalPaid >= $this->membership->price_paid;

            // 1. Update data Membership Utama
            $this->membership->update([
                'total_paid' => $newTotalPaid,
                'payment_status' => $isLunas ? 'paid' : 'partial',
                // Otomatis aktifkan akses gym kalau lunas, kalau nyicil tetap status sebelumnya
                'status' => $isLunas ? 'active' : $this->membership->status, 
            ]);

            // 2. Ambil data transaksi SEBELUMNYA untuk menjaga konsistensi
            $previousTransaction = $this->membership->transactions()->latest()->first();

            if ($previousTransaction) {
                // Ambil data dari transaksi lama
                $packageNameStr = $previousTransaction->package_name;
                $transactionStartDate = $previousTransaction->start_date;
                $transactionEndDate = $previousTransaction->end_date;
            } else {
                // Fallback (jaga-jaga kalau error tidak ada transaksi sebelumnya)
                $packageNameStr = 'Paket Custom';
                if ($this->membership->type === 'visit') $packageNameStr = 'Visit Harian';
                elseif ($this->membership->gym_package_id) $packageNameStr = $this->membership->gymPackage->name ?? 'Paket Gym';
                elseif ($this->membership->pt_package_id) $packageNameStr = $this->membership->ptPackage->name ?? 'Paket PT';

                $transactionStartDate = $this->membership->start_date;
                $transactionEndDate = in_array($this->membership->type, ['pt']) ? $this->membership->pt_end_date : $this->membership->membership_end_date;
            }


            // 3. Catat ke Tabel Buku Kasir
            MembershipTransaction::create([
                'invoice_number' => 'INV-' . date('Ymd') . '-' . strtoupper(uniqid()),
                'membership_id' => $this->membership->id,
                'user_id' => $this->membership->user_id,
                'admin_id' => $this->membership->admin_id, 
                'follow_up_id' => $this->membership->follow_up_id ?: null,
                'transaction_type' => $this->transaction_type,
                'package_name' => $packageNameStr,
                'amount' => $this->amount_paid,
                'payment_method' => $this->payment_method,
                'payment_date' => $this->payment_date,
                'start_date' => $transactionStartDate,
                'end_date' => $transactionEndDate,
            ]);

            DB::commit();

            session()->flash('success', $isLunas ? 'Pembayaran berhasil dan tagihan LUNAS! Akses gym sudah aktif.' : 'Pembayaran cicilan berhasil dicatat.');
            return $this->redirectRoute('admin.penjualan.index', navigate: true);

        } catch (\Exception $e) {
            DB::rollBack();
            session()->flash('error', 'Terjadi kesalahan sistem: ' . $e->getMessage());
        }
    }
}

?>

<div>
    <div class="mb-6 flex items-center gap-3">
        <a href="{{ route('admin.cicilan.index') }}" wire:navigate class="p-2 bg-white border border-default rounded-md hover:bg-gray-50 text-gray-600 transition-colors">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
        </a>
        <div>
            <h5 class="text-xl font-semibold text-heading mb-1">Pembayaran Cicilan</h5>
            <p class="text-body text-sm">Lanjutkan pembayaran untuk member: <span class="font-semibold text-brand-strong">{{ $membership->user->name }}</span></p>
        </div>
    </div>

    @if (session()->has('error'))
        <div class="mb-4 p-4 text-sm text-red-800 rounded-lg bg-red-50 border border-red-200">
            {{ session('error') }}
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        {{-- KOLOM KIRI: Info Paket & Riwayat Pembayaran --}}
        <div class="lg:col-span-2 space-y-6">
            
            <div class="bg-white p-6 shadow-xs rounded-md border border-default">
                <h6 class="text-md font-semibold text-heading mb-4 pb-2 border-b border-default-medium">Detail Paket yang Diambil</h6>
                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <span class="block text-gray-500 mb-1">Total Harga Paket</span>
                        <span class="font-bold text-lg text-heading">Rp {{ number_format($membership->price_paid, 0, ',', '.') }}</span>
                    </div>
                    <div>
                        <span class="block text-gray-500 mb-1">Sisa Tagihan (Utang) Saat Ini</span>
                        <span class="font-bold text-lg text-red-600">Rp {{ number_format($this->sisaTagihan, 0, ',', '.') }}</span>
                    </div>
                    <div>
                        <span class="block text-gray-500 mb-1">Status Keanggotaan</span>
                        @if($membership->status === 'active')
                            <span class="bg-green-100 text-green-800 text-xs font-medium px-2.5 py-0.5 rounded">Aktif</span>
                        @else
                            <span class="bg-orange-100 text-orange-800 text-xs font-medium px-2.5 py-0.5 rounded">Pending (Belum Lunas)</span>
                        @endif
                    </div>
                    <div>
                        <span class="block text-gray-500 mb-1">Tanggal Daftar</span>
                        <span class="font-medium text-heading">{{ \Carbon\Carbon::parse($membership->start_date)->format('d M Y') }}</span>
                    </div>
                </div>
            </div>

            <div class="bg-white p-6 shadow-xs rounded-md border border-default">
                <h6 class="text-md font-semibold text-heading mb-4 pb-2 border-b border-default-medium">Riwayat Pembayaran Sebelumnya</h6>
                <div class="space-y-3">
                    @forelse($membership->transactions as $trx)
                        <div class="flex justify-between items-center p-3 bg-gray-50 rounded border border-gray-100">
                            <div>
                                <div class="font-semibold text-sm text-heading">{{ $trx->transaction_type }}</div>
                                <div class="text-xs text-gray-500 mt-0.5">{{ \Carbon\Carbon::parse($trx->payment_date)->format('d M Y') }} • {{ strtoupper($trx->payment_method) }}</div>
                            </div>
                            <div class="font-bold text-green-600">
                                + Rp {{ number_format($trx->amount, 0, ',', '.') }}
                            </div>
                        </div>
                    @empty
                        <div class="text-sm text-gray-500 text-center py-4">Belum ada riwayat pembayaran yang tercatat.</div>
                    @endforelse
                </div>
                
            </div>

        </div>

        {{-- KOLOM KANAN: Form Kasir Pembayaran Cicilan --}}
        <div>
            <form wire:submit="save" class="bg-neutral-primary-soft p-6 shadow-xs rounded-md border border-default sticky top-6">
                <h6 class="text-lg font-semibold text-heading mb-4 pb-4 border-b border-default-medium">Input Pembayaran</h6>

                <div class="space-y-4 mb-6">
                    
                    {{-- Input Uang Diterima (Menggunakan Alpine.js titik persis seperti di form Create) --}}
                    <div>
                        <label class="block mb-1 text-sm font-medium text-heading">Nominal yang Dibayar (Rp)</label>
                        <div x-data="{ 
                            amount: $wire.entangle('amount_paid').live, 
                            maxAmount: {{ $this->sisaTagihan }}, // Ambil batas maksimal dari PHP
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
                                
                                // JIKA ANGKA YANG DIKETIK MELEBIHI SISA TAGIHAN
                                if (raw !== '') {
                                    let numValue = parseInt(raw, 10);
                                    if (numValue > this.maxAmount) {
                                        raw = this.maxAmount.toString(); // Paksa kembali ke batas maksimal
                                    }
                                }

                                this.amount = raw;
                                this.formatValue(raw);
                            }
                        }">
                            <input type="text" 
                                x-model="formatted" 
                                @input="updateValue($event)"
                                class="bg-white border border-default-medium text-heading text-lg font-bold rounded-md focus:ring-brand focus:border-brand block w-full px-3 py-2 shadow-xs text-green-600" 
                                placeholder="Contoh: 150.000" required>
                        </div>
                        @error('amount_paid') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                        
                        {{-- Logika Menampilkan Tulisan LUNAS atau Sisa Utang --}}
                        @if($amount_paid >= $this->sisaTagihan)
                            <div class="bg-green-50 text-green-700 text-xs px-3 py-2 rounded mt-2 font-medium border border-green-200">
                                ✅ Tagihan akan LUNAS dengan pembayaran ini.
                            </div>
                        @elseif($amount_paid > 0)
                            <div class="bg-orange-50 text-orange-700 text-xs px-3 py-2 rounded mt-2 font-medium border border-orange-200">
                                Sisa Utang Nanti: Rp {{ number_format($this->sisaTagihan - $amount_paid, 0, ',', '.') }}
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

                    {{-- Catatan Opsional --}}
                    <div>
                        <label class="block mb-1 text-sm font-medium text-heading">Status</label>
                        <textarea wire:model="transaction_type" rows="2" class="bg-white border border-default-medium text-heading text-sm rounded-md focus:ring-brand focus:border-brand block w-full px-3 py-2 shadow-xs placeholder-gray-400" placeholder="Contoh: PELUNASAN NEW MEMBER" required></textarea>
                        @error('transaction_type') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                </div>

                <button 
                    type="submit" 
                    class="w-full text-center text-white bg-brand hover:bg-brand-strong focus:ring-4 focus:ring-brand-medium font-medium rounded-md text-sm px-4 py-3 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                    wire:loading.attr="disabled"
                >
                    <span wire:loading.remove>Simpan Pembayaran</span>
                    <span wire:loading>Memproses...</span>
                </button>
            </form>
        </div>

    </div>
</div>