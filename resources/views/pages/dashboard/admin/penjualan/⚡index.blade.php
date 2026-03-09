<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use App\Models\MembershipTransaction;
use Carbon\Carbon;

new #[Layout('layouts::admin')] class extends Component
{
    use WithPagination;

    public $search = '';
    public $filterTime = 'today';
    public $dateStart;
    public $dateEnd;
    
    // Filter Baru
    public $perPage = 'all';
    public $shift = 'pagi';

    public function setFilterTime($val)
    {
        $this->filterTime = $val;
        $this->resetPage();
    }

    public function setDateRange($dateStr)
    {
        $dates = explode(' to ', $dateStr);
        if (count($dates) === 2) {
            $this->dateStart = $dates[0];
            $this->dateEnd = $dates[1];
            $this->filterTime = 'custom';
            $this->resetPage();
        }
    }

    public function updatedPerPage() { $this->resetPage(); }
    public function updatedShift() { $this->resetPage(); }

    /**
     * Membuat Query dasar untuk dipakai tabel dan Grand Total
     */
    private function getBaseQuery()
    {
        $query = MembershipTransaction::with(['user', 'admin', 'membership.members']);

        // 1. Logika Pencarian
        if (!empty($this->search)) {
            $query->where(function ($q) {
                $q->whereHas('user', function ($subQ) {
                    $subQ->where('name', 'like', '%' . $this->search . '%');
                })
                ->orWhere('invoice_number', 'like', '%' . $this->search . '%')
                ->orWhere('package_name', 'like', '%' . $this->search . '%');
            });
        }

        // 2. Logika Filter Waktu
        if ($this->filterTime === 'today') {
            $query->whereDate('payment_date', today());
        } elseif ($this->filterTime === 'week') {
            $query->whereBetween('payment_date', [now()->startOfWeek(), now()->endOfWeek()]);
        } elseif ($this->filterTime === 'month') {
            $query->whereMonth('payment_date', now()->month)
                  ->whereYear('payment_date', now()->year);
        } elseif ($this->filterTime === 'custom' && $this->dateStart && $this->dateEnd) {
            $query->whereBetween('payment_date', [
                $this->dateStart . ' 00:00:00', 
                $this->dateEnd . ' 23:59:59'
            ]);
        }

        // 3. Logika Filter Shift (Jam)
        // Pagi = 06:00 - 14:59 | Siang = 15:00 - 23:59
        if ($this->shift === 'pagi') {
            // Cari transaksi yang dikerjakan oleh admin dengan shift 'Pagi'
            $query->whereHas('admin', function ($q) {
                $q->where('shift', 'Pagi');
            });
        } elseif ($this->shift === 'siang') {
            // Cari transaksi yang dikerjakan oleh admin dengan shift 'Siang'
            $query->whereHas('admin', function ($q) {
                $q->where('shift', 'Siang');
            });
        }

        return $query;
    }

    #[Computed]
    public function transactions()
    {
        $query = $this->getBaseQuery();
        $limit = $this->perPage === 'all' ? 10000 : $this->perPage;
        return $query->latest('payment_date')->paginate($limit);
    }

    #[Computed]
    public function summary()
    {
        // Ambil semua data sesuai filter yang aktif (tanpa batasan per halaman)
        $data = $this->getBaseQuery()->get();

        // Keuangan (Kiri)
        $transfer = $data->where('payment_method', 'transfer')->sum('amount');
        $debit = $data->where('payment_method', 'edc')->sum('amount'); 
        $qris = $data->where('payment_method', 'qris')->sum('amount');
        $cash = $data->where('payment_method', 'cash')->sum('amount');
        
        $totalSystemBalance = $transfer + $debit + $qris + $cash;
        
        $pengeluaran = 0; // Saat ini masih 0 karena belum ada modul pengeluaran
        $realCash = $cash - $pengeluaran; 

        // Statistik Uang Berdasarkan Kategori Paket (Kanan)
        $visitData = $data->filter(fn($item) => stripos($item->package_name, 'visit') !== false);
        $ptData = $data->filter(fn($item) => stripos($item->package_name, 'pt') !== false || stripos($item->package_name, 'trainer') !== false);
        
        $totalUangVisit = $visitData->sum('amount');
        $totalUangPT = $ptData->sum('amount');
        $totalUangMember = $totalSystemBalance - $totalUangVisit - $totalUangPT; // Sisa uang dipastikan masuk ke Gym Member

        return [
            'transfer' => $transfer,
            'debit' => $debit,
            'qris' => $qris,
            'cash' => $cash,
            'balance_merah' => $totalSystemBalance,
            'pengeluaran' => $pengeluaran,
            'real_cash' => $realCash,
            'balance_hijau' => $totalSystemBalance - $pengeluaran,
            
            // Perubahan di sini 👇
            'uang_member' => $totalUangMember,
            'uang_visit' => $totalUangVisit,
            'uang_pt' => $totalUangPT,
            'uang_total' => $totalSystemBalance, 
        ];
    }
};
?>

<div>
    <div class="flex sm:flex-row flex-col justify-between items-center mb-6">
        <h5 class="text-xl font-semibold text-heading">Riwayat Penjualan</h5>
        <div class="flex gap-2">
            {{-- Tombol Export dll --}}
        </div>
    </div>

    {{-- Filter Bar --}}
    <div class="relative overflow-x-auto bg-neutral-primary-soft shadow-xs rounded-md border border-default mb-6">
        <div class="p-4 flex flex-col lg:flex-row items-center justify-between gap-4">
            
            {{-- Search --}}
            <div class="relative w-full lg:w-auto flex-1">
                <div class="absolute inset-y-0 start-0 flex items-center ps-3 pointer-events-none">
                    <svg class="w-4 h-4 text-body" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-width="2" d="m21 21-3.5-3.5M17 10a7 7 0 1 1-14 0 7 7 0 0 1 14 0Z"/></svg>
                </div>
                <input type="text" wire:model.live.debounce.300ms="search" class="block w-full ps-9 pe-3 py-2.5 bg-white border border-default-medium text-heading text-sm rounded-md focus:ring-brand focus:border-brand shadow-xs" placeholder="Cari nama, atau paket...">
            </div>
            
        </div>
        <div class="flex flex-wrap items-center gap-3 w-full lg:w-auto px-4">
                
                {{-- Dropdown Per Page --}}
                <select wire:model.live="perPage" class="bg-white border border-default-medium text-heading text-sm rounded-md focus:ring-brand focus:border-brand pr-6 py-2.5 shadow-xs">
                    <option value="10">10 Baris</option>
                    <option value="50">50 Baris</option>
                    <option value="all">Semua</option>
                </select>

                {{-- Dropdown Shift --}}
                <select wire:model.live="shift" class="bg-white border border-default-medium text-heading text-sm rounded-md focus:ring-brand focus:border-brand px-3 py-2.5 shadow-xs pr-6">
                    <option value="all">Semua Shift</option>
                    <option value="pagi">Shift Pagi</option>
                    <option value="siang">Shift Siang</option>
                </select>

                {{-- Datepicker Custom (Tetap sama seperti sebelumnya) --}}
                <div class="relative w-full sm:w-56" wire:ignore>
                    <div class="absolute inset-y-0 start-0 flex items-center ps-3 pointer-events-none">
                        <svg class="w-4 h-4 text-body" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 10h16M8 14h8m-4-7V4M7 7V4m10 3V4M5 20h14a1 1 0 0 0 1-1V7a1 1 0 0 0-1-1H5a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1Z"/></svg>
                    </div>
                    <input type="text" x-data x-init="flatpickr($el, { mode: 'range', dateFormat: 'Y-m-d', placeholder: 'Pilih Tanggal', onClose: function(selectedDates, dateStr) { @this.call('setDateRange', dateStr) } })" class="block w-full ps-9 pe-3 py-2.5 bg-white border border-default-medium text-heading text-sm rounded-md focus:ring-brand focus:border-brand shadow-xs" placeholder="Pilih Rentang Tanggal">
                </div>

                {{-- Filter Presets (Hari ini, Bulan ini) --}}
                <div x-data="{ open: false }" class="relative">
                    <button @click="open = !open" @click.outside="open = false" class="inline-flex items-center justify-center text-body bg-white border border-default-medium hover:bg-gray-50 focus:ring-4 focus:ring-gray-100 shadow-xs font-medium rounded-md text-sm px-3 py-2.5" type="button">
                        <svg class="w-4 h-4 me-1.5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-width="2" d="M18.796 4H5.204a1 1 0 0 0-.753 1.659l5.302 6.058a1 1 0 0 1 .247.659v4.874a.5.5 0 0 0 .2.4l3 2.25a.5.5 0 0 0 .8-.4v-7.124a1 1 0 0 1 .247-.659l5.302-6.059c.566-.646.106-1.658-.753-1.658Z"/></svg>
                        @if($filterTime === 'today') Hari Ini
                        @elseif($filterTime === 'week') Minggu Ini
                        @elseif($filterTime === 'month') Bulan Ini
                        @elseif($filterTime === 'custom') Kustom
                        @else Semua Waktu @endif
                        <svg class="w-4 h-4 ms-1.5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m19 9-7 7-7-7"/></svg>
                    </button>
                    
                    <div x-show="open" style="display: none;" class="absolute right-0 z-50 mt-2 bg-white border border-gray-200 rounded-md shadow-lg w-40">
                        <ul class="p-2 text-sm text-gray-700 font-medium">
                            <li><button type="button" wire:click="setFilterTime('all')" @click="open = false" class="w-full text-left p-2 hover:bg-gray-100 rounded">Semua Waktu</button></li>
                            <li><button type="button" wire:click="setFilterTime('today')" @click="open = false" class="w-full text-left p-2 hover:bg-gray-100 rounded">Hari ini</button></li>
                            <li><button type="button" wire:click="setFilterTime('week')" @click="open = false" class="w-full text-left p-2 hover:bg-gray-100 rounded">Minggu ini</button></li>
                            <li><button type="button" wire:click="setFilterTime('month')" @click="open = false" class="w-full text-left p-2 hover:bg-gray-100 rounded">Bulan ini</button></li>
                        </ul>
                    </div>
                </div>
            </div>
        
        <table class="w-full text-sm text-left rtl:text-right text-body">
            <thead class="text-sm text-body bg-neutral-secondary-medium border-b border-default-medium">
                <tr>
                    <th class="px-6 py-3 font-medium">No</th>
                    <th class="px-6 py-3 font-medium">Nama</th>
                    <th class="px-6 py-3 font-medium">Tanggal Bayar</th>
                    <th class="px-6 py-3 font-medium">Tgl Mulai Aktif</th>
                    <th class="px-6 py-3 font-medium text-right">Tgl Berakhir</th>
                    <th class="px-6 py-3 font-medium">Status</th>
                    <th class="px-6 py-3 font-medium">Paket Member</th>
                    <th class="px-6 py-3 font-medium text-right">Nominal</th>
                    <th class="px-6 py-3 font-medium">Metode Bayar</th>
                    <th class="px-6 py-3 font-medium">Admin</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->transactions as $transaction)
                    <tr wire:key="{{ $transaction->id }}" class="bg-white border-b border-gray-100 hover:bg-gray-50">
                        <td class="px-6 py-4">{{ $loop->iteration + ($this->transactions->currentPage() - 1) * $this->transactions->perPage() }}</td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex flex-col gap-1">
                                {{-- Cek apakah transaksi punya relasi membership dan ada anggotanya --}}
                                @if($transaction->membership && $transaction->membership->members->count() > 0)
                                    @foreach($transaction->membership->members as $member)
                                        <div class="font-bold text-gray-800">
                                            {{ $member->name }}
                                        </div>
                                    @endforeach
                                @else
                                    {{-- Fallback: Jika tidak ada di pivot, tampilkan nama pembayar saja --}}
                                    <div class="font-bold text-gray-800">
                                        {{ $transaction->user->name ?? 'User Terhapus' }}
                                    </div>
                                @endif
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="font-medium">{{ $transaction->payment_date ? \Carbon\Carbon::parse($transaction->payment_date)->format('d M Y') : '-' }}</div>
                            <div class="text-xs text-gray-500">{{ $transaction->payment_date ? \Carbon\Carbon::parse($transaction->payment_date)->format('H:i') : '' }} WIB</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">{{ $transaction->start_date ? \Carbon\Carbon::parse($transaction->start_date)->format('d M Y') : '-' }}</td>
                        <td class="px-6 py-4 text-right whitespace-nowrap">{{ $transaction->end_date ? \Carbon\Carbon::parse($transaction->end_date)->format('d M Y') : '-' }}</td>
                        <td class="px-6 py-4 whitespace-nowrap"><span class="px-2 py-0.5 text-[10px] uppercase font-bold rounded-full bg-blue-100 text-blue-800">{{ $transaction->transaction_type }}</span></td>
                        <td class="px-6 py-4 font-medium text-gray-700 whitespace-nowrap">{{ $transaction->package_name }}</td>
                        <td class="px-6 py-4 text-right font-bold text-emerald-600 whitespace-nowrap">Rp {{ number_format($transaction->amount, 0, ',', '.') }}</td>
                        <td class="px-6 py-4 whitespace-nowrap"><span class="text-xs font-medium border bg-white px-2 py-0.5 rounded shadow-xs">{{ strtoupper($transaction->payment_method) }}</span></td>
                        <td class="px-6 py-4 whitespace-nowrap">{{ $transaction->admin->name ?? '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="10" class="px-6 py-8 text-center text-gray-500">Belum ada riwayat penjualan.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    
    <div class="mb-6">
        {{ $this->transactions->links() }}
    </div>

    {{-- KOTAK GRAND TOTAL DAN STATISTIK --}}
    {{-- SATU TABEL BESAR: GRAND TOTAL & KATEGORI --}}
    <div class="mb-10 bg-white shadow-sm border border-gray-200 rounded-lg overflow-hidden">
        
        {{-- Judul Besar --}}
        {{-- Judul Besar --}}
        <div class="bg-green-600 text-white font-bold px-4 py-3 text-lg flex justify-center items-center">
            <span>
                GRAND TOTAL (SHIFT {{ strtoupper($shift === 'all' ? 'PAGI & SIANG' : $shift) }})
            </span>
            
        </div>
        
        <div class="p-0 overflow-x-auto">
            <table class="w-full text-sm text-left text-gray-700">
                
                <tbody>
                    <tr class="border-b border-gray-100">
                        <td class="px-4 py-3 font-medium">Total Transfer</td>
                        <td class="px-4 py-3 text-right font-bold">Rp {{ number_format($this->summary['transfer'], 0, ',', '.') }}</td>
                        
                        <td class="px-4 py-3 font-medium border-l border-gray-200">🏋️ Total Member Gym</td>
                        <td class="px-4 py-3 text-right font-bold">Rp {{ number_format($this->summary['uang_member'], 0, ',', '.') }}</td>
                    </tr>
                    <tr class="border-b border-gray-100">
                        <td class="px-4 py-3 font-medium">Total Debit (EDC)</td>
                        <td class="px-4 py-3 text-right font-bold">Rp {{ number_format($this->summary['debit'], 0, ',', '.') }}</td>
                        
                        <td class="px-4 py-3 font-medium border-l border-gray-200">🎟️ Total Visit Harian</td>
                        <td class="px-4 py-3 text-right font-bold">Rp {{ number_format($this->summary['uang_visit'], 0, ',', '.') }}</td>
                    </tr>
                    <tr class="border-b border-gray-100">
                        <td class="px-4 py-3 font-medium">Total QRIS</td>
                        <td class="px-4 py-3 text-right font-bold">Rp {{ number_format($this->summary['qris'], 0, ',', '.') }}</td>
                        
                        <td class="px-4 py-3 font-medium border-l border-gray-200">👨‍🏫 Total Personal Trainer</td>
                        <td class="px-4 py-3 text-right font-bold">Rp {{ number_format($this->summary['uang_pt'], 0, ',', '.') }}</td>
                    </tr>
                    <tr class="border-b border-gray-100">
                        <td class="px-4 py-3 font-medium">Cash On Hand</td>
                        <td class="px-4 py-3 text-right font-bold">Rp {{ number_format($this->summary['cash'], 0, ',', '.') }}</td>
                        
                        {{-- Balance Kategori Pendapatan (Sisi Kanan) --}}
                        <td class="px-4 py-3 bg-emerald-50 text-emerald-800 font-bold uppercase tracking-wide border-l border-gray-200">Balance (Pendapatan)</td>
                        <td class="px-4 py-3 bg-emerald-50 text-emerald-800 text-right font-black text-lg">Rp {{ number_format($this->summary['uang_total'], 0, ',', '.') }}</td>
                    </tr>
                    
                    {{-- Balance Masuk Kas (Sisi Kiri) --}}
                    <tr class="border-b border-gray-100 bg-red-50 text-red-700">
                        <td class="px-4 py-3 font-bold uppercase tracking-wide">Balance (Total Masuk)</td>
                        <td class="px-4 py-3 text-right font-bold text-base">Rp {{ number_format($this->summary['balance_merah'], 0, ',', '.') }}</td>
                        
                        {{-- Ruang kosong di sisi kanan karena datanya sudah habis --}}
                        <td colspan="2" rowspan="4" class="bg-gray-50 border-l border-gray-200 align-top p-4 text-center text-gray-400">
                            <span class="block mt-4 italic text-xs">* Total nominal di Kategori Pendapatan otomatis sama dengan Total Uang Masuk.</span>
                        </td>
                    </tr>
                    
                    {{-- Pengeluaran & Real Cash (Sisi Kiri) --}}
                    <tr class="border-b border-gray-100 bg-white">
                        <td class="px-4 py-3 font-medium">Pengeluaran</td>
                        <td class="px-4 py-3 text-right font-bold text-red-600">- Rp {{ number_format($this->summary['pengeluaran'], 0, ',', '.') }}</td>
                    </tr>
                    <tr class="border-b border-gray-100 bg-white">
                        <td class="px-4 py-3 font-medium">Real Cash</td>
                        <td class="px-4 py-3 text-right font-bold">Rp {{ number_format($this->summary['real_cash'], 0, ',', '.') }}</td>
                    </tr>
                    
                    {{-- Balance Final (Sisi Kiri) --}}
                    <tr class="bg-emerald-100 text-emerald-800">
                        <td class="px-4 py-4 font-bold uppercase tracking-wide text-lg">Balance Akhir</td>
                        <td class="px-4 py-4 text-right font-black text-xl">Rp {{ number_format($this->summary['balance_hijau'], 0, ',', '.') }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>