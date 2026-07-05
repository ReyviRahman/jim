<?php

namespace App\Livewire\Pages\Dashboard\Admin\Beverages;

use App\Exports\BeverageSaleExport;
use App\Exports\BeverageSaleExportDetail;
use App\Models\BeverageSale;
use App\Models\DepositBeverage;
use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;

new #[Layout('layouts::admin')] class extends Component
{
    use WithPagination;

    public $searchProduct = '';
    public $filterTime = 'today';
    public $dateStart;
    public $dateEnd;
    public $shift = '';
    public $showDeleteModal = false;
    public $selectedSaleId = null;
    public $selectedSale = null;

    public function mount()
    {
        $this->dateStart = now()->format('Y-m-d');
        $this->dateEnd = now()->format('Y-m-d');
    }

    public function setFilterTime($val)
    {
        $this->filterTime = $val;
        $this->resetPage();
    }

    public function setDateRange($dateStr)
    {
        if (empty($dateStr)) {
            return;
        }

        $dates = explode(' to ', $dateStr);

        if (count($dates) === 2) {
            $this->dateStart = $dates[0];
            $this->dateEnd = $dates[1];
        } elseif (count($dates) === 1) {
            $this->dateStart = $dates[0];
            $this->dateEnd = $dates[0];
        }

        $this->filterTime = 'custom';
        $this->resetPage();
    }

    private function getBaseQuery()
    {
        $query = BeverageSale::with([
            'beverage' => function ($query) {
                $query->withTrashed();
            },
            'depositBeverage',
        ])
        ->when($this->searchProduct, function ($query) {
            $query->where(function ($q) {
                $q->whereHas('beverage', function ($q2) {
                    $q2->where('nama_produk', 'like', '%' . $this->searchProduct . '%');
                })
                ->orWhere('nama_staff', 'like', '%' . $this->searchProduct . '%')
                ->orWhere('nama_penghutang', 'like', '%' . $this->searchProduct . '%')
                ->orWhere('nama_produk', 'like', '%' . $this->searchProduct . '%')
                ->orWhereHas('depositBeverage', function ($q2) {
                    $q2->where('nama_pelanggan', 'like', '%' . $this->searchProduct . '%');
                });
            });
        })
        ->when($this->shift, function ($query) {
            $query->where('shift', $this->shift);
        });

        // Filter Waktu
        if ($this->filterTime === 'today') {
            $this->dateStart = now()->format('Y-m-d');
            $this->dateEnd = now()->format('Y-m-d');
            $query->whereDate('waktu_transaksi', today());
        } elseif ($this->filterTime === 'week') {
            $this->dateStart = now()->startOfWeek()->format('Y-m-d');
            $this->dateEnd = now()->endOfWeek()->format('Y-m-d');
            $query->whereBetween('waktu_transaksi', [now()->startOfWeek(), now()->endOfWeek()]);
        } elseif ($this->filterTime === 'month') {
            $this->dateStart = now()->startOfMonth()->format('Y-m-d');
            $this->dateEnd = now()->endOfMonth()->format('Y-m-d');
            $query->whereMonth('waktu_transaksi', now()->month)
                  ->whereYear('waktu_transaksi', now()->year);
        } elseif ($this->filterTime === 'custom' && $this->dateStart && $this->dateEnd) {
            $query->whereDate('waktu_transaksi', '>=', $this->dateStart)
                  ->whereDate('waktu_transaksi', '<=', $this->dateEnd);
        }

        return $query;
    }

    public function getSalesProperty()
    {
        return $this->getBaseQuery()
            ->latest()
            ->paginate(10);
    }

    #[Computed]
    public function summary()
    {
        $data = $this->getBaseQuery()->get();

        $cash = $data->where('keterangan_bayar', 'cash')->sum('total_harga') + $data->where('keterangan_bayar', 'cash')->sum('save_deposit');
        $transfer = $data->where('keterangan_bayar', 'tf_bca_qris')->sum('total_harga');
        $deposit = $data->where('keterangan_bayar', 'deposit')->sum('total_harga') + $data->where('keterangan_bayar', '!=', 'deposit')->sum('deposit_amount');
        $depositHutangCash = $data->where('keterangan_bayar', 'deposit_hutang_cash')->sum('total_harga');
        $depositHutangQris = $data->where('keterangan_bayar', 'deposit_hutang_qris')->sum('total_harga');
        $operasional = $data->where('keterangan_bayar', 'operasional')->sum('total_harga');
        $pengeluaranUmum = $data->where('keterangan_bayar', 'pengeluaran_umum')->sum('total_harga');
        $hutang = $data->where('keterangan_bayar', 'hutang')->sum('total_harga');

        $rincianPengeluaran = $data->where('keterangan_bayar', 'pengeluaran_umum')->values();

        $totalPengeluaran = $pengeluaranUmum;
        $totalMasuk = $cash + $transfer + $depositHutangCash + $depositHutangQris;

        $penjualan = $cash + $transfer;

        $realCash = $cash - $totalPengeluaran;
        $balanceHijau = $totalMasuk - $totalPengeluaran;

        return [
            'cash' => $cash,
            'transfer' => $transfer,
            'deposit' => $deposit,
            'deposit_hutang_cash' => $depositHutangCash,
            'deposit_hutang_qris' => $depositHutangQris,
            'operasional' => $operasional,
            'pengeluaran_umum' => $pengeluaranUmum,
            'hutang' => $hutang,
            'total_pengeluaran' => $totalPengeluaran,
            'total_masuk' => $totalMasuk,
            'penjualan' => $penjualan,
            'real_cash' => $realCash,
            'balance_hijau' => $balanceHijau,
            'rincian_pengeluaran' => $rincianPengeluaran,
        ];
    }

    public function exportExcel()
    {
        $fileName = 'penjualan_minuman_' . date('Y-m-d_His') . '.xlsx';

        return Excel::download(
            new BeverageSaleExport(
                $this->searchProduct,
                $this->dateStart,
                $this->dateEnd
            ),
            $fileName
        );
    }

    public function exportExcelDetail()
    {
        $fileName = 'penjualan_minuman_detail_' . date('Y-m-d_His') . '.xlsx';

        return Excel::download(
            new BeverageSaleExportDetail(
                $this->searchProduct,
                $this->dateStart,
                $this->dateEnd
            ),
            $fileName
        );
    }

    public function confirmDelete($id)
    {
        $this->selectedSaleId = $id;
        $this->selectedSale = BeverageSale::with(['depositBeverage', 'parentBeverageSale', 'changeDeposit'])->find($id);
        $this->showDeleteModal = true;
    }

    public function deleteSale()
    {
        $sale = BeverageSale::with(['depositBeverage', 'parentBeverageSale'])->find($this->selectedSaleId);
        if (!$sale) {
            $this->closeDeleteModal();
            return;
        }

        if ($sale->keterangan_bayar === 'cash') {
            $changeDeposit = DepositBeverage::where('beverage_sale_id', $sale->id)->first();

            if ($changeDeposit) {
                if ($changeDeposit->is_used || $changeDeposit->sisa_nominal !== $changeDeposit->nominal) {
                    session()->flash('error', 'Tidak dapat menghapus transaksi karena deposit kembaliannya sudah digunakan.');
                    $this->closeDeleteModal();

                    return;
                }

                $changeDeposit->delete();
            }
        }

        $stockAffecting = !in_array($sale->keterangan_bayar, ['deposit_hutang_cash', 'deposit_hutang_qris', 'pengeluaran_umum']);

        if ($stockAffecting && $sale->beverage) {
            $beverage = $sale->beverage;
            $beverage->update([
                'stok_sekarang' => $beverage->stok_sekarang + $sale->jumlah_beli,
            ]);
        }

        if ($sale->depositBeverage && $sale->deposit_amount > 0) {
            $deposit = $sale->depositBeverage;
            $deposit->update([
                'sisa_nominal' => $deposit->sisa_nominal + $sale->deposit_amount,
                'is_used' => false,
            ]);
        }

        if ($sale->parentBeverageSale && in_array($sale->keterangan_bayar, ['deposit_hutang_cash', 'deposit_hutang_qris'])) {
            $sale->parentBeverageSale->update(['is_lunas' => false]);
        }

        $sale->delete();

        $message = $stockAffecting ? 'Data penjualan berhasil dihapus. Stok minuman dikembalikan.' : 'Data penjualan berhasil dihapus.';
        session()->flash('success', $message);

        $this->closeDeleteModal();
    }

    public function closeDeleteModal()
    {
        $this->showDeleteModal = false;
        $this->selectedSaleId = null;
        $this->selectedSale = null;
    }

    public function with(): array
    {
        return [];
    }
};
?>

<div>
    <div class="flex sm:flex-row flex-col justify-between items-center mb-6">
        <h5 class="text-xl font-semibold text-heading">Riwayat Penjualan Minuman</h5>
    </div>

    <div class="bg-neutral-primary-soft shadow-xs rounded-md border border-default">
        <div class="p-4 border-b border-default-medium">
            <div class="flex flex-col sm:flex-row justify-between items-center gap-4">
                <h6 class="text-lg font-semibold text-heading">Daftar Transaksi</h6>
                <div class="flex flex-col sm:flex-row gap-2 w-full sm:w-auto">
                    {{-- Datepicker Custom --}}
                    <div class="relative w-full sm:w-56" wire:ignore>
                        <div class="absolute inset-y-0 start-0 flex items-center ps-3 pointer-events-none">
                            <svg class="w-4 h-4 text-body" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 10h16M8 14h8m-4-7V4M7 7V4m10 3V4M5 20h14a1 1 0 0 0 1-1V7a1 1 0 0 0-1-1H5a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1Z"/></svg>
                        </div>
                        <input type="text" x-data x-init="flatpickr($el, { mode: 'range', dateFormat: 'Y-m-d', placeholder: 'Pilih Tanggal', onClose: function(selectedDates, dateStr) { $wire.setDateRange(dateStr) } })" class="block w-full ps-9 pe-3 py-2.5 bg-white border border-default-medium text-heading text-sm rounded-md focus:ring-brand focus:border-brand shadow-xs" placeholder="Pilih Rentang Tanggal">
                    </div>

                    <select wire:model.live="shift"
                        class="pe-8 py-1.5 bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base focus:ring-brand focus:border-brand shadow-xs">
                        <option value="">Semua Shift</option>
                        <option value="pagi">Pagi</option>
                        <option value="siang">Siang</option>
                    </select>

                    {{-- Filter Presets --}}
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
                    {{-- <button type="button" wire:click="exportExcel"
                        class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white text-sm rounded-base font-medium inline-flex items-center gap-1">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                            <polyline points="7 10 12 15 17 10"/>
                            <line x1="12" x2="12" y1="15" y2="3"/>
                        </svg>
                        Export
                    </button> --}}
                    <button type="button" wire:click="exportExcelDetail"
                        class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-sm rounded-base font-medium inline-flex items-center gap-1">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                            <polyline points="7 10 12 15 17 10"/>
                            <line x1="12" x2="12" y1="15" y2="3"/>
                        </svg>
                        Export
                    </button>
                </div>
            </div>
            <div class="mt-3">
                <input type="text" wire:model.live="searchProduct"
                    class="block w-full px-3 py-2 bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base focus:ring-brand focus:border-brand shadow-xs placeholder:text-body"
                    placeholder="Cari nama produk atau staff...">
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left rtl:text-right text-body">
                <thead class="text-sm text-body bg-neutral-secondary-medium border-b border-default-medium">
                    <tr>
                        <th scope="col" class="px-4 py-3 font-medium">Tanggal</th>
                        <th scope="col" class="px-4 py-3 font-medium">Staff</th>
                        <th scope="col" class="px-4 py-3 font-medium">Nama Pelanggan</th>
                        <th scope="col" class="px-4 py-3 font-medium">Produk</th>
                        <th scope="col" class="px-4 py-3 font-medium text-center">Jumlah</th>
                        <th scope="col" class="px-4 py-3 font-medium text-right">Harga Satuan</th>
                        <th scope="col" class="px-4 py-3 font-medium text-right">Total</th>
                        <th scope="col" class="px-4 py-3 font-medium">Shift</th>
                        <th scope="col" class="px-4 py-3 font-medium">Metode Bayar</th>
                        <th scope="col" class="px-4 py-3 font-medium text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->sales as $sale)
                        @php
                            $bgColor = match($sale->keterangan_bayar) {
                                'operasional' => 'bg-blue-50',
                                'pengeluaran_umum' => 'bg-red-50',
                                'deposit_hutang_cash', 'deposit_hutang_qris' => 'bg-yellow-50',
                                'deposit' => 'bg-indigo-50',
                                'hutang' => 'bg-purple-50',
                                default => 'bg-neutral-primary-soft',
                            };
                        @endphp
                        <tr class="{{ $bgColor }} border-b border-default hover:bg-neutral-secondary-medium">
                            <td class="px-4 py-3 whitespace-nowrap">{{ $sale->waktu_transaksi->format('d M Y H:i') }}</td>
                            <td class="px-4 py-3 whitespace-nowrap">{{ $sale->nama_staff }}</td>
                            <td class="px-4 py-3 whitespace-nowrap">{{ $sale->nama_penghutang ?? '-' }}</td>
                            <td class="px-4 py-3 whitespace-nowrap">{{ $sale->nama_produk ?? '-' }}</td>
                            <td class="px-4 py-3 text-center whitespace-nowrap">{{ $sale->jumlah_beli }}</td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">Rp {{ number_format($sale->harga_satuan, 0, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right whitespace-nowrap font-semibold text-emerald-600">
                                Rp {{ number_format($sale->total_harga + ($sale->save_deposit ?? 0), 0, ',', '.') }}
                                @if($sale->save_deposit > 0)
                                    <span class="block text-[10px] text-body font-normal">(termasuk deposit Rp {{ number_format($sale->save_deposit, 0, ',', '.') }})</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">{{ ucfirst($sale->shift) }}</td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                @php
                                    $metode = [
                                        'cash' => 'Cash',
                                        'tf_bca_qris' => 'TF BCA/Qris',
                                        'operasional' => 'Operasional',
                                        'pengeluaran_umum' => 'Pengeluaran Umum',
                                        'deposit_hutang_cash' => 'Pelunasan Hutang (Cash)',
                                        'deposit_hutang_qris' => 'Pelunasan Hutang (QRIS)',
                                        'hutang' => 'Hutang',
                                        'deposit' => 'Deposit',
                                    ];
                                    $badge = match($sale->keterangan_bayar) {
                                        'cash' => 'bg-emerald-100 text-emerald-700',
                                        'tf_bca_qris' => 'bg-blue-100 text-blue-700',
                                        'operasional' => 'bg-blue-100 text-blue-700',
                                        'pengeluaran_umum' => 'bg-red-100 text-red-700',
                                        'deposit_hutang_cash', 'deposit_hutang_qris' => 'bg-yellow-100 text-yellow-700',
                                        'deposit' => 'bg-indigo-100 text-indigo-700',
                                        'hutang' => 'bg-purple-100 text-purple-700',
                                        default => 'bg-gray-100 text-gray-700',
                                    };
                                @endphp
                                <span class="px-2 py-1 rounded-full text-xs font-semibold {{ $badge }}">
                                    {{ $metode[$sale->keterangan_bayar] ?? $sale->keterangan_bayar }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center whitespace-nowrap">
                                @if(auth()->check() && auth()->user()->role === 'admin')
                                    <button type="button" wire:click="confirmDelete({{ $sale->id }})" class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium text-red-700 bg-red-50 border border-red-200 rounded-md hover:bg-red-100 focus:ring-2 focus:ring-red-300 transition-colors">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/></svg>
                                        Hapus
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="px-4 py-8 text-center text-gray-500">Belum ada riwayat penjualan.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4 px-4">
            {{ $this->sales->links() }}
        </div>
    </div>

    {{-- KOTAK GRAND TOTAL --}}
    <div class="mt-6 mb-10 bg-white shadow-sm border border-gray-200 rounded-lg overflow-hidden">
        <div class="bg-green-600 text-white font-bold px-4 py-3 text-lg flex justify-center items-center">
            <span>
                GRAND TOTAL
                @if($shift) (SHIFT {{ strtoupper($shift) }}) @endif
                @if($dateStart && $dateEnd && $dateStart !== $dateEnd)
                    ({{ \Carbon\Carbon::parse($dateStart)->format('d M Y') }} - {{ \Carbon\Carbon::parse($dateEnd)->format('d M Y') }})
                @elseif($dateStart)
                    ({{ \Carbon\Carbon::parse($dateStart)->format('d M Y') }})
                @endif
            </span>
        </div>
        
        <div class="p-0 overflow-x-auto">
            <table class="w-full text-sm text-left text-gray-700">
                <tbody>
                    <tr class="border-b border-gray-100">
                        <td class="px-4 py-3 font-medium">PENJUALAN (CASH)</td>
                        <td class="px-4 py-3 text-right font-bold">Rp {{ number_format($this->summary['cash'], 0, ',', '.') }}</td>
                        
                        <td class="px-4 py-3 font-medium border-l border-gray-200">OPERASIONAL</td>
                        <td class="px-4 py-3 text-right font-bold">Rp {{ number_format($this->summary['operasional'], 0, ',', '.') }}</td>
                    </tr>
                    <tr class="border-b border-gray-100">
                        <td class="px-4 py-3 font-medium">PENJUALAN (TF BCA/QRIS)</td>
                        <td class="px-4 py-3 text-right font-bold">Rp {{ number_format($this->summary['transfer'], 0, ',', '.') }}</td>
                        
                        <td class="px-4 py-3 font-medium border-l border-gray-200">PENGELUARAN</td>
                        <td class="px-4 py-3 text-right font-bold text-red-600">- Rp {{ number_format($this->summary['total_pengeluaran'], 0, ',', '.') }}</td>
                    </tr>
                    <tr class="border-b border-gray-100">
                        <td class="px-4 py-3 font-medium">DEPOSIT</td>
                        <td class="px-4 py-3 text-right font-bold">Rp {{ number_format($this->summary['deposit'], 0, ',', '.') }}</td>
                        
                        <td class="px-4 py-3 font-medium border-l border-gray-200">HUTANG</td>
                        <td class="px-4 py-3 text-right font-bold">Rp {{ number_format($this->summary['hutang'], 0, ',', '.') }}</td>
                    </tr>
                    <tr class="border-b border-gray-100">
                        <td class="px-4 py-3 font-medium">PELUNASAN HUTANG (CASH)</td>
                        <td class="px-4 py-3 text-right font-bold">Rp {{ number_format($this->summary['deposit_hutang_cash'], 0, ',', '.') }}</td>

                        <td class="px-4 py-3 font-medium border-l border-gray-200"></td>
                        <td class="px-4 py-3 text-right font-bold"></td>
                    </tr>
                    <tr class="border-b border-gray-100">
                        <td class="px-4 py-3 font-medium">PELUNASAN HUTANG (QRIS)</td>
                        <td class="px-4 py-3 text-right font-bold">Rp {{ number_format($this->summary['deposit_hutang_qris'], 0, ',', '.') }}</td>

                        <td class="px-4 py-3 font-medium border-l border-gray-200"></td>
                        <td class="px-4 py-3 text-right font-bold"></td>
                    </tr>
                    
                    <tr class="border-b border-gray-100 bg-red-50 text-red-700">
                        <td class="px-4 py-3 font-bold uppercase tracking-wide">BALANCE</td>
                        <td class="px-4 py-3 text-right font-bold text-base">Rp {{ number_format($this->summary['total_masuk'], 0, ',', '.') }}</td>
                        <td colspan="2" rowspan="4" class="bg-gray-50 border-l border-gray-200 align-top p-4">
                            <div class="font-bold text-gray-700 mb-3 border-b border-gray-200 pb-2 text-sm uppercase tracking-wide">
                                RINCIAN PENGELUARAN
                            </div>
                            
                            @if(count($this->summary['rincian_pengeluaran']) > 0)
                                <ul class="space-y-2.5 text-xs text-gray-600 max-h-48 overflow-y-auto pr-2">
                                    @foreach($this->summary['rincian_pengeluaran'] as $exp)
                                        <li class="flex justify-between items-start gap-3 border-b border-gray-100 pb-2 last:border-0 last:pb-0">
                                            <div class="flex-1">
                                                <span class="block font-semibold text-gray-800">{{ $exp->nama_produk ?? 'Tidak ada nama' }}</span>
                                                <span class="text-gray-500 text-[10px] mt-0.5 block">{{ $exp->waktu_transaksi->format('d M Y H:i') }}</span>
                                                @if($exp->nama_penghutang)
                                                    <span class="text-gray-400 text-[10px] block">Oleh: {{ $exp->nama_penghutang }}</span>
                                                @endif
                                            </div>
                                            <span class="font-bold text-red-600 whitespace-nowrap">- Rp {{ number_format($exp->total_harga, 0, ',', '.') }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            @else
                                <div class="text-center text-gray-400 text-xs italic mt-6">
                                    Tidak ada pengeluaran.
                                </div>
                            @endif
                        </td>
                    </tr>
                    
                    <tr class="border-b border-gray-100 bg-white">
                        <td class="px-4 py-3 font-medium">PENGELUARAN</td>
                        <td class="px-4 py-3 text-right font-bold text-red-600">- Rp {{ number_format($this->summary['total_pengeluaran'], 0, ',', '.') }}</td>
                    </tr>
                    <tr class="border-b border-gray-100 bg-white">
                        <td class="px-4 py-3 font-medium">REAL CASH</td>
                        <td class="px-4 py-3 text-right font-bold">Rp {{ number_format($this->summary['real_cash'], 0, ',', '.') }}</td>
                    </tr>
                    
                    <tr class="bg-emerald-100 text-emerald-800">
                        <td class="px-4 py-4 font-bold uppercase tracking-wide text-lg">BALANCE</td>
                        <td class="px-4 py-4 text-right font-black text-xl">Rp {{ number_format($this->summary['balance_hijau'], 0, ',', '.') }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    @if(auth()->check() && auth()->user()->role === 'admin')
        @if ($showDeleteModal)
            <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" wire:click.self="closeDeleteModal">
                <div class="bg-white rounded-lg shadow-xl w-full max-w-md mx-4 p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h5 class="text-lg font-semibold text-heading">Konfirmasi Hapus</h5>
                        <button type="button" wire:click="closeDeleteModal" class="text-gray-400 hover:text-gray-600">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"></path><path d="m6 6 12 12"></path></svg>
                        </button>
                    </div>
                    <p class="text-body mb-4">Apakah Anda yakin ingin menghapus data penjualan ini? Stok minuman akan dikembalikan.</p>

                    @if($selectedSale && $selectedSale->depositBeverage && $selectedSale->deposit_amount > 0)
                        <div class="mb-4 p-3 bg-amber-50 border border-amber-200 rounded-md text-sm text-amber-800">
                            <p class="font-semibold mb-1">Transaksi ini menggunakan deposit:</p>
                            <p>Pelanggan: {{ $selectedSale->depositBeverage->nama_pelanggan }}</p>
                            <p>Deposit akan dikembalikan: Rp {{ number_format($selectedSale->deposit_amount, 0, ',', '.') }}</p>
                        </div>
                    @endif

                    @if($selectedSale && $selectedSale->parentBeverageSale && in_array($selectedSale->keterangan_bayar, ['deposit_hutang_cash', 'deposit_hutang_qris']))
                        <div class="mb-4 p-3 bg-yellow-50 border border-yellow-200 rounded-md text-sm text-yellow-800">
                            <p class="font-semibold mb-1">Transaksi ini adalah pelunasan hutang:</p>
                            <p>Penghutang: {{ $selectedSale->parentBeverageSale->nama_penghutang }}</p>
                            <p>Hutang asli akan kembali menjadi belum lunas setelah dihapus.</p>
                        </div>
                    @endif

                    @if($selectedSale && $selectedSale->changeDeposit)
                        <div class="mb-4 p-3 bg-cyan-50 border border-cyan-200 rounded-md text-sm text-cyan-800">
                            <p class="font-semibold mb-1">Transaksi ini menghasilkan deposit kembalian:</p>
                            <p>Pelanggan: {{ $selectedSale->changeDeposit->nama_pelanggan }}</p>
                            <p>Nominal: Rp {{ number_format($selectedSale->changeDeposit->nominal, 0, ',', '.') }}</p>
                            @if($selectedSale->changeDeposit->is_used || $selectedSale->changeDeposit->sisa_nominal !== $selectedSale->changeDeposit->nominal)
                                <p class="font-semibold text-red-600 mt-1">Deposit sudah digunakan, transaksi tidak dapat dihapus.</p>
                            @else
                                <p>Deposit akan ikut dihapus.</p>
                            @endif
                        </div>
                    @endif

                    <div class="flex items-center justify-end gap-3">
                        <button type="button" wire:click="closeDeleteModal" class="px-4 py-2 text-sm font-medium text-body bg-neutral-secondary-medium border border-default-medium rounded-md hover:bg-neutral-secondary-strong transition-colors">
                            Batal
                        </button>
                        <button type="button" wire:click="deleteSale"
                            @if($selectedSale && $selectedSale->changeDeposit && ($selectedSale->changeDeposit->is_used || $selectedSale->changeDeposit->sisa_nominal !== $selectedSale->changeDeposit->nominal)) disabled @endif
                            class="px-4 py-2.5 text-white bg-red-600 hover:bg-red-700 rounded-md font-medium text-sm focus:outline-none disabled:opacity-50 disabled:cursor-not-allowed">
                            Hapus
                        </button>
                    </div>
                </div>
            </div>
        @endif
    @endif
</div>