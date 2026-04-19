<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use App\Models\MembershipTransaction;
use App\Models\Expense;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\PenjualanExport;
use Illuminate\Support\Facades\Auth;

new #[Layout('layouts::admin')] class extends Component
{
    use WithPagination;

    public $search = '';
    public $filterTime = 'today';
    public $dateStart;
    public $dateEnd;
    
    // Filter Baru
    public $perPage = 'all';
    public $shift;

    public function mount()
    {
        $user = Auth::user();
        // Cek apakah user sudah login untuk menghindari error
        if ($user) {
            // Jika role admin, set ke 'all'
            if ($user->role === 'admin') {
                $this->shift = 'all';
            } else {
                // Jika bukan admin, ambil dari kolom shift milik user tersebut di database
                // Asumsi di database kamu ada kolom bernama 'shift' (berisi 'pagi' atau 'siang')
                $this->shift = $user->shift; 
            }
        } else {
            // Fallback default jika user belum login (opsional, sesuaikan kebutuhan)
            $this->shift = 'pagi';
        }
    }

    public function setFilterTime($val)
    {
        $this->filterTime = $val;
        $this->resetPage();
    }

    public function setDateRange($dateStr)
    {
        // Hindari memproses string kosong jika user menekan clear
        if (empty($dateStr)) {
            return; 
        }

        $dates = explode(' to ', $dateStr);

        if (count($dates) === 2) {
            // Jika user memilih rentang 2 tanggal (contoh: 2023-10-01 to 2023-10-05)
            $this->dateStart = $dates[0];
            $this->dateEnd = $dates[1];
        } elseif (count($dates) === 1) {
            // Jika user hanya memilih 1 tanggal (contoh: 2023-10-01)
            $this->dateStart = $dates[0];
            $this->dateEnd = $dates[0]; // Jadikan start dan end sama
        }

        // Terapkan filter dan reset halaman
        $this->filterTime = 'custom';
        $this->resetPage();
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
            $this->dateStart = now()->format('Y-m-d');
            $this->dateEnd = now()->format('Y-m-d');
            $query->whereDate('payment_date', today());
        } elseif ($this->filterTime === 'week') {
            $this->dateStart = now()->startOfWeek()->format('Y-m-d');
            $this->dateEnd = now()->endOfWeek()->format('Y-m-d');
            $query->whereBetween('payment_date', [now()->startOfWeek(), now()->endOfWeek()]);
        } elseif ($this->filterTime === 'month') {
            $this->dateStart = now()->startOfMonth()->format('Y-m-d');
            $this->dateEnd = now()->endOfMonth()->format('Y-m-d');
            $query->whereMonth('payment_date', now()->month)
                  ->whereYear('payment_date', now()->year);
        } elseif ($this->filterTime === 'custom' && $this->dateStart && $this->dateEnd) {
            $query->whereDate('payment_date', '>=', $this->dateStart)
                  ->whereDate('payment_date', '<=', $this->dateEnd);
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
        $query = $this->getBaseQuery()->with(['admin', 'followUp', 'followUpTwo']);
        $limit = $this->perPage === 'all' ? 10000 : $this->perPage;
        return $query->latest('payment_date')->paginate($limit);
    }

    #[Computed]
    public function summary()
    {
        // ==========================================
        // 1. DATA PEMASUKAN (MembershipTransactions)
        // ==========================================
        // Ambil semua data pemasukan sesuai filter yang aktif
        $data = $this->getBaseQuery()->get();

        // Keuangan (Kiri)
        $transfer = $data->where('payment_method', 'transfer')->sum('amount');
        $debit = $data->where('payment_method', 'debit')->sum('amount'); 
        $qris = $data->where('payment_method', 'qris')->sum('amount');
        $cash = $data->where('payment_method', 'cash')->sum('amount');
        
        $totalSystemBalance = $transfer + $debit + $qris + $cash;
        
        // Statistik Uang Berdasarkan Kategori Paket (Kanan)
        $visitData = $data->filter(fn($item) => stripos($item->package_name, 'visit') !== false);
        $ptData = $data->filter(fn($item) => stripos($item->package_name, 'pt') !== false || stripos($item->package_name, 'trainer') !== false);
        
        $totalUangVisit = $visitData->sum('amount');
        $totalUangPT = $ptData->sum('amount');
        $totalUangMember = $totalSystemBalance - $totalUangVisit - $totalUangPT; 

        // ==========================================
        // 2. DATA PENGELUARAN (Expenses)
        // ==========================================
        // Buat query baru khusus untuk tabel pengeluaran
        $expenseQuery = Expense::query();

        // Terapkan Filter Waktu ke Pengeluaran
        if ($this->filterTime === 'today') {
            $expenseQuery->whereDate('expense_date', today());
        } elseif ($this->filterTime === 'week') {
            $expenseQuery->whereBetween('expense_date', [now()->startOfWeek(), now()->endOfWeek()]);
        } elseif ($this->filterTime === 'month') {
            $expenseQuery->whereMonth('expense_date', now()->month)
                         ->whereYear('expense_date', now()->year);
        } elseif ($this->filterTime === 'custom' && $this->dateStart && $this->dateEnd) {
            $expenseQuery->whereDate('expense_date', '>=', $this->dateStart)
                         ->whereDate('expense_date', '<=', $this->dateEnd);
        }

        // Terapkan Filter Shift ke Pengeluaran (Jika ada)
        if ($this->shift === 'pagi') {
            $expenseQuery->whereHas('admin', function ($q) {
                $q->where('shift', 'Pagi');
            });
        } elseif ($this->shift === 'siang') {
            $expenseQuery->whereHas('admin', function ($q) {
                $q->where('shift', 'Siang');
            });
        }

        $rincianPengeluaran = $expenseQuery->with('admin')->get();
        // Hitung Total Pengeluaran dari data rincian tersebut
        $pengeluaran = $rincianPengeluaran->sum('amount');
        $realCash = $cash - $pengeluaran;

        return [
            'transfer' => $transfer,
            'debit' => $debit,
            'qris' => $qris,
            'cash' => $cash,
            'balance_merah' => $totalSystemBalance, // Total Masuk
            'pengeluaran' => $pengeluaran,          // Total Keluar
            'real_cash' => $realCash,               // Sisa Uang Fisik (Cash - Pengeluaran)
            'balance_hijau' => $totalSystemBalance - $pengeluaran, // Laba Bersih Keseluruhan
            
            'uang_member' => $totalUangMember,
            'uang_visit' => $totalUangVisit,
            'uang_pt' => $totalUangPT,
            'uang_total' => $totalSystemBalance, 
            'rincian_pengeluaran' => $rincianPengeluaran,
        ];
    }

    public function exportExcel()
    {
        // 1. Ambil data pemasukan
        $transactions = $this->getBaseQuery()->with(['admin', 'followUp', 'followUpTwo'])->get();

        $startDate = $this->dateStart;
        $endDate = $this->dateEnd;

        // 2. Hitung komponen uang pemasukan
        $transfer = $transactions->where('payment_method', 'transfer')->sum('amount');
        $debit = $transactions->where('payment_method', 'debit')->sum('amount');
        $qris = $transactions->where('payment_method', 'qris')->sum('amount');
        $cash = $transactions->where('payment_method', 'cash')->sum('amount');
        $totalSystemBalance = $transfer + $debit + $qris + $cash;

        $visitData = $transactions->filter(fn($item) => stripos($item->package_name, 'visit') !== false);
        $ptData = $transactions->filter(fn($item) => stripos($item->package_name, 'pt') !== false || stripos($item->package_name, 'trainer') !== false);
        
        $uangVisit = $visitData->sum('amount');
        $uangPT = $ptData->sum('amount');
        $uangMember = $totalSystemBalance - $uangVisit - $uangPT;

        // 3. Ambil data pengeluaran dengan filter yang sama persis
        $expenseQuery = \App\Models\Expense::query();
        if ($this->filterTime === 'today') {
            $expenseQuery->whereDate('expense_date', today());
        } elseif ($this->filterTime === 'week') {
            $expenseQuery->whereBetween('expense_date', [now()->startOfWeek(), now()->endOfWeek()]);
        } elseif ($this->filterTime === 'month') {
            $expenseQuery->whereMonth('expense_date', now()->month)->whereYear('expense_date', now()->year);
        } elseif ($this->filterTime === 'custom' && $this->dateStart && $this->dateEnd) {
            $expenseQuery->whereDate('expense_date', '>=', $this->dateStart)
                         ->whereDate('expense_date', '<=', $this->dateEnd);
        }
        if ($this->shift === 'pagi') {
            $expenseQuery->whereHas('admin', fn($q) => $q->where('shift', 'Pagi'));
        } elseif ($this->shift === 'siang') {
            $expenseQuery->whereHas('admin', fn($q) => $q->where('shift', 'Siang'));
        }
        
        $rincianPengeluaran = $expenseQuery->with('admin')->get();
        $pengeluaran = $rincianPengeluaran->sum('amount');

        // 4. Jadikan satu array summaryTotal
        $summaryTotal = [
            'transfer' => $transfer, 'debit' => $debit, 'qris' => $qris, 'cash' => $cash,
            'balance_merah' => $totalSystemBalance,
            'pengeluaran' => $pengeluaran,
            'real_cash' => $cash - $pengeluaran,
            'balance_hijau' => $totalSystemBalance - $pengeluaran,
            'uang_member' => $uangMember, 'uang_visit' => $uangVisit, 'uang_pt' => $uangPT,
            'uang_total' => $totalSystemBalance,
            'rincian_pengeluaran' => $rincianPengeluaran // Rincian teks pengeluaran dilempar ke Excel
        ];

        // 5. Download Excel-nya
        // Jika 1 hari saja
        // Ubah format tanggal menjadi d-m-Y (Tanggal-Bulan-Tahun)
        $formatStart = \Carbon\Carbon::parse($startDate)->format('d-m-Y');
        $formatEnd = \Carbon\Carbon::parse($endDate)->format('d-m-Y');

        if ($startDate === $endDate) {
            $fileName = 'Laporan_Penjualan_' . $formatStart . '.xlsx';
        } 
        else {
            $fileName = 'Laporan_Penjualan_' . $formatStart . '_sd_' . $formatEnd . '.xlsx';
        }

        return \Maatwebsite\Excel\Facades\Excel::download(new PenjualanExport(
            $transactions, 
            $summaryTotal, 
            $startDate,
            $endDate,
            $this->shift 
        ), $fileName);
    }
};
?>

<div>
    


    <div class="flex sm:flex-row flex-col justify-between items-center mb-6">
        <h5 class="text-xl font-semibold text-heading">
            Riwayat Penjualan Shift {{ $this->shift === 'all' ? 'Pagi & Siang' : ucfirst($this->shift) }}
        </h5>
        <div class="flex gap-2">
            <button wire:click="exportExcel" wire:loading.attr="disabled" class="inline-flex items-center justify-center text-white bg-emerald-600 border border-transparent hover:bg-emerald-700 focus:ring-4 focus:ring-emerald-300 shadow-xs font-medium rounded-md text-sm px-4 py-2.5 focus:outline-none disabled:opacity-50">
                <svg class="w-4 h-4 me-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3M3 17V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z"></path></svg>
                <span wire:loading.remove wire:target="exportExcel">Export Excel</span>
                <span wire:loading wire:target="exportExcel">Memproses...</span>
            </button>
        </div>
    </div>
    @if (session()->has('success'))
        <div class="p-4 mb-4 text-sm text-green-800 rounded-lg bg-green-50" role="alert">
            <span class="font-medium">Sukses!</span> {{ session('success') }}
        </div>
    @endif
    @if (session()->has('error'))
        <div class="p-4 mb-4 text-sm text-red-800 rounded-lg bg-red-50" role="alert">
            <span class="font-medium">Gagal!</span> {{ session('error') }}
        </div>
    @endif

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
                    <input type="text" x-data x-init="flatpickr($el, { mode: 'range', dateFormat: 'Y-m-d', placeholder: 'Pilih Tanggal', onClose: function(selectedDates, dateStr) { $wire.setDateRange(dateStr) } })" class="block w-full ps-9 pe-3 py-2.5 bg-white border border-default-medium text-heading text-sm rounded-md focus:ring-brand focus:border-brand shadow-xs" placeholder="Pilih Rentang Tanggal">
                </div>

                {{-- Filter Presets (Hari ini, Bulan ini) --}}
                <div x-data="{ open: false }" class="relative">
                    <button @click="open = !open" @click.outside="open = false" class="inline-flex items-center justify-center text-body bg-white border border-default-medium hover:bg-gray-50 focus:ring-4 focus:ring-gray-100 shadow-xs font-medium rounded-md text-sm px-3 py-2.5" type="button">
                        <svg class="w-4 h-4 me-1.5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-width="2" d="M18.796 4H5.204a1 1 0 0 0-.753 1.659l5.302 6.058a1 1 0 0 1 .247.659v4.874a.5.5 0 0 0 .2.4l3 2.25a.5.5 0 0 0 .8-.4v-7.124a1 1 0 0 1 .247-.659l5.302-6.059c.566-.646.106-1.658-.753-1.658Z"/></svg>
                        @if($filterTime === 'today') Hari Ini
                        @elseif($filterTime === 'week') Minggu Ini
                        @elseif($filterTime === 'month') Bulan Ini
                        @elseif($filterTime === 'custom') Kustom @endif
                        <svg class="w-4 h-4 ms-1.5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m19 9-7 7-7-7"/></svg>
                    </button>
                    
                    <div x-show="open" style="display: none;" class="absolute right-0 z-50 mt-2 bg-white border border-gray-200 rounded-md shadow-lg w-40">
                        <ul class="p-2 text-sm text-gray-700 font-medium">
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
                    <th class="px-6 py-3 font-medium">Catatan</th>
                    <th class="px-6 py-3 font-medium text-right">Nominal</th>
                    <th class="px-6 py-3 font-medium">Metode Bayar</th>
                    <th class="px-6 py-3 font-medium">Admin Follow Up</th>
                    <th class="px-6 py-3 font-medium">Sales Follow Up</th>
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
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">{{ $transaction->start_date ? \Carbon\Carbon::parse($transaction->start_date)->format('d M Y') : 'BELUM AKTIF' }}</td>
                        <td class="px-6 py-4 text-right whitespace-nowrap">{{ $transaction->end_date ? \Carbon\Carbon::parse($transaction->end_date)->format('d M Y') : 'BELUM AKTIF' }}</td>
                        <td class="px-6 py-4 whitespace-nowrap"><span class="px-2 py-0.5 text-[10px] uppercase font-bold rounded-full bg-blue-100 text-blue-800">{{ $transaction->transaction_type }}</span></td>
                        <td class="px-6 py-4 font-medium text-gray-700 whitespace-nowrap">{{ $transaction->package_name }}</td>
                        <td class="px-6 py-4 font-medium text-gray-700 whitespace-nowrap">{{ $transaction->notes }}</td>
                        <td class="px-6 py-4 text-right font-bold text-emerald-600 whitespace-nowrap">Rp {{ number_format($transaction->amount, 0, ',', '.') }}</td>
                        <td class="px-6 py-4 whitespace-nowrap"><span class="text-xs font-medium border bg-white px-2 py-0.5 rounded shadow-xs">{{ strtoupper($transaction->payment_method) }}</span></td>
                        <td class="px-6 py-4 whitespace-nowrap">{{ $transaction->followUp->name ?? '-' }}</td>
                        <td class="px-6 py-4 whitespace-nowrap">{{ $transaction->followUpTwo->name ?? '-' }}</td>
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
                        <td class="px-4 py-3 font-medium">TRANSFER BCA</td>
                        <td class="px-4 py-3 text-right font-bold">Rp {{ number_format($this->summary['transfer'], 0, ',', '.') }}</td>
                        
                        <td class="px-4 py-3 font-medium border-l border-gray-200">MEMBER</td>
                        <td class="px-4 py-3 text-right font-bold">Rp {{ number_format($this->summary['uang_member'], 0, ',', '.') }}</td>
                    </tr>
                    <tr class="border-b border-gray-100">
                        <td class="px-4 py-3 font-medium">DEBIT BCA</td>
                        <td class="px-4 py-3 text-right font-bold">Rp {{ number_format($this->summary['debit'], 0, ',', '.') }}</td>
                        
                        <td class="px-4 py-3 font-medium border-l border-gray-200">VISIT</td>
                        <td class="px-4 py-3 text-right font-bold">Rp {{ number_format($this->summary['uang_visit'], 0, ',', '.') }}</td>
                    </tr>
                    <tr class="border-b border-gray-100">
                        <td class="px-4 py-3 font-medium">QRIS BCA</td>
                        <td class="px-4 py-3 text-right font-bold">Rp {{ number_format($this->summary['qris'], 0, ',', '.') }}</td>
                        
                        <td class="px-4 py-3 font-medium border-l border-gray-200">PERSONAL TRAINER</td>
                        <td class="px-4 py-3 text-right font-bold">Rp {{ number_format($this->summary['uang_pt'], 0, ',', '.') }}</td>
                    </tr>
                    <tr class="border-b border-gray-100">
                        <td class="px-4 py-3 font-medium">CASH ON HAND</td>
                        <td class="px-4 py-3 text-right font-bold">Rp {{ number_format($this->summary['cash'], 0, ',', '.') }}</td>
                        
                        {{-- Balance Kategori Pendapatan (Sisi Kanan) --}}
                        <td class="px-4 py-3 bg-emerald-50 text-emerald-800 font-bold uppercase tracking-wide border-l border-gray-200">BALANCE</td>
                        <td class="px-4 py-3 bg-emerald-50 text-emerald-800 text-right font-black text-lg">Rp {{ number_format($this->summary['uang_total'], 0, ',', '.') }}</td>
                    </tr>
                    
                    {{-- Balance Masuk Kas (Sisi Kiri) --}}
                    <tr class="border-b border-gray-100 bg-red-50 text-red-700">
                        <td class="px-4 py-3 font-bold uppercase tracking-wide">BALANCE</td>
                        <td class="px-4 py-3 text-right font-bold text-base">Rp {{ number_format($this->summary['balance_merah'], 0, ',', '.') }}</td>
                        
                        {{-- RINCIAN PENGELUARAN (Sisi Kanan) --}}
                        <td colspan="2" rowspan="4" class="bg-gray-50 border-l border-gray-200 align-top p-4">
                            <div class="font-bold text-gray-700 mb-3 border-b border-gray-200 pb-2 text-sm uppercase tracking-wide">
                                CATATAN
                            </div>
                            
                            @if(count($this->summary['rincian_pengeluaran']) > 0)
                                <ul class="space-y-2.5 text-xs text-gray-600 max-h-32 overflow-y-auto pr-2">
                                    @foreach($this->summary['rincian_pengeluaran'] as $exp)
                                        <li class="flex justify-between items-start gap-3 border-b border-gray-100 pb-2 last:border-0 last:pb-0">
                                            <div class="flex-1">
                                                <span class="block font-semibold text-gray-800">{{ $exp->description }}</span>
                                                <span class="text-gray-500 text-[10px] mt-0.5 block">Admin: {{ $exp->admin->name ?? '-' }}</span>
                                            </div>
                                            <span class="font-bold text-red-600 whitespace-nowrap">- Rp {{ number_format($exp->amount, 0, ',', '.') }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            @else
                                <div class="text-center text-gray-400 text-xs italic mt-6">
                                    Tidak ada catatan pengeluaran.
                                </div>
                            @endif
                            
                        </td>
                    </tr>
                    
                    {{-- Pengeluaran & Real Cash (Sisi Kiri) --}}
                    <tr class="border-b border-gray-100 bg-white">
                        <td class="px-4 py-3 font-medium">PENGELUARAN</td>
                        <td class="px-4 py-3 text-right font-bold text-red-600">- Rp {{ number_format($this->summary['pengeluaran'], 0, ',', '.') }}</td>
                    </tr>
                    <tr class="border-b border-gray-100 bg-white">
                        <td class="px-4 py-3 font-medium">REAL CASH</td>
                        <td class="px-4 py-3 text-right font-bold">Rp {{ number_format($this->summary['real_cash'], 0, ',', '.') }}</td>
                    </tr>
                    
                    {{-- Balance Final (Sisi Kiri) --}}
                    <tr class="bg-emerald-100 text-emerald-800">
                        <td class="px-4 py-4 font-bold uppercase tracking-wide text-lg">BALANCE</td>
                        <td class="px-4 py-4 text-right font-black text-xl">Rp {{ number_format($this->summary['balance_hijau'], 0, ',', '.') }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>