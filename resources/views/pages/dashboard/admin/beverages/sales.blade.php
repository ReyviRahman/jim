<?php

namespace App\Livewire\Pages\Dashboard\Admin\Beverages;

use App\Exports\BeverageSaleExport;
use App\Exports\BeverageSaleExportDetail;
use App\Models\BeverageSale;
use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;

new #[Layout('layouts::admin')] class extends Component
{
    use WithPagination;

    public $searchProduct = '';
    public $start_date = '';
    public $shift = '';
    public $showDeleteModal = false;
    public $selectedSaleId = null;

    public function mount()
    {
        $this->start_date = date('Y-m-d');
    }

    public function getSalesProperty()
    {
        return BeverageSale::with(['beverage' => function ($query) {
            $query->withTrashed();
        }])
        ->when($this->searchProduct, function ($query) {
            $query->where(function ($q) {
                $q->whereHas('beverage', function ($q2) {
                    $q2->where('nama_produk', 'like', '%' . $this->searchProduct . '%');
                })
                ->orWhere('nama_staff', 'like', '%' . $this->searchProduct . '%');
            });
        })
        ->when($this->start_date, function ($query) {
            $query->whereDate('waktu_transaksi', $this->start_date);
        })
        ->when($this->shift, function ($query) {
            $query->where('shift', $this->shift);
        })
        ->latest()
        ->paginate(10);
    }

    #[Computed]
    public function summary()
    {
        $query = BeverageSale::query()
            ->when($this->searchProduct, function ($query) {
                $query->where(function ($q) {
                    $q->whereHas('beverage', function ($q2) {
                        $q2->where('nama_produk', 'like', '%' . $this->searchProduct . '%');
                    })
                    ->orWhere('nama_staff', 'like', '%' . $this->searchProduct . '%');
                });
            })
            ->when($this->start_date, function ($query) {
                $query->whereDate('waktu_transaksi', $this->start_date);
            })
            ->when($this->shift, function ($query) {
                $query->where('shift', $this->shift);
            });

        $data = $query->get();

        $cash = $data->where('keterangan_bayar', 'cash')->sum('total_harga');
        $transfer = $data->where('keterangan_bayar', 'tf_bca_qris')->sum('total_harga');
        $depositCash = $data->where('keterangan_bayar', 'deposit_hutang_cash')->sum('total_harga');
        $depositQris = $data->where('keterangan_bayar', 'deposit_hutang_qris')->sum('total_harga');
        $operasional = $data->where('keterangan_bayar', 'operasional')->sum('total_harga');
        $pengeluaranUmum = $data->where('keterangan_bayar', 'pengeluaran_umum')->sum('total_harga');
        $hutang = $data->where('keterangan_bayar', 'hutang')->sum('total_harga');

        $rincianPengeluaran = $data->where('keterangan_bayar', 'pengeluaran_umum')->values();

        $totalCash = $cash + $depositCash;
        $totalTransfer = $transfer + $depositQris;
        $totalPengeluaran = $pengeluaranUmum;
        $totalMasuk = $cash + $transfer + $depositCash + $depositQris;

        $penjualan = $cash + $transfer;

        $realCash = $totalCash - $totalPengeluaran;
        $balanceHijau = $totalMasuk - $totalPengeluaran;

        return [
            'cash' => $cash,
            'transfer' => $transfer,
            'deposit_cash' => $depositCash,
            'deposit_qris' => $depositQris,
            'operasional' => $operasional,
            'pengeluaran_umum' => $pengeluaranUmum,
            'hutang' => $hutang,
            'total_cash' => $totalCash,
            'total_transfer' => $totalTransfer,
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
                $this->start_date
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
                $this->start_date
            ),
            $fileName
        );
    }

    public function confirmDelete($id)
    {
        $this->selectedSaleId = $id;
        $this->showDeleteModal = true;
    }

    public function deleteSale()
    {
        $sale = BeverageSale::find($this->selectedSaleId);
        if (!$sale) {
            $this->closeDeleteModal();
            return;
        }

        $stockAffecting = !in_array($sale->keterangan_bayar, ['deposit_hutang_cash', 'deposit_hutang_qris', 'operasional', 'pengeluaran_umum']);

        if ($stockAffecting && $sale->beverage) {
            $beverage = $sale->beverage;
            $beverage->update([
                'stok_sekarang' => $beverage->stok_sekarang + $sale->jumlah_beli,
            ]);
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
                    <input type="date" wire:model.live="start_date"
                        class="px-2 py-1.5 bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base focus:ring-brand focus:border-brand shadow-xs">
                    <select wire:model.live="shift"
                        class="pe-8 py-1.5 bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base focus:ring-brand focus:border-brand shadow-xs">
                        <option value="">Semua Shift</option>
                        <option value="pagi">Pagi</option>
                        <option value="siang">Siang</option>
                    </select>
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
                            <td class="px-4 py-3 text-right whitespace-nowrap font-semibold text-emerald-600">Rp {{ number_format($sale->total_harga, 0, ',', '.') }}</td>
                            <td class="px-4 py-3 whitespace-nowrap">{{ ucfirst($sale->shift) }}</td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                @php
                                    $metode = [
                                        'cash' => 'Cash',
                                        'tf_bca_qris' => 'TF BCA/Qris',
                                        'operasional' => 'Operasional',
                                        'pengeluaran_umum' => 'Pengeluaran Umum',
                                        'deposit_hutang_cash' => 'Deposit/Cash',
                                        'deposit_hutang_qris' => 'Deposit/QRIS',
                                        'hutang' => 'Hutang',
                                    ];
                                    $badge = match($sale->keterangan_bayar) {
                                        'cash' => 'bg-emerald-100 text-emerald-700',
                                        'tf_bca_qris' => 'bg-blue-100 text-blue-700',
                                        'operasional' => 'bg-blue-100 text-blue-700',
                                        'pengeluaran_umum' => 'bg-red-100 text-red-700',
                                        'deposit_hutang_cash', 'deposit_hutang_qris' => 'bg-yellow-100 text-yellow-700',
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
            <span>GRAND TOTAL @if($shift) (SHIFT {{ strtoupper($shift) }}) @endif</span>
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
                        <td class="px-4 py-3 font-medium">PELUNASAN HUTANG (CASH)</td>
                        <td class="px-4 py-3 text-right font-bold">Rp {{ number_format($this->summary['deposit_cash'], 0, ',', '.') }}</td>
                        
                        <td class="px-4 py-3 font-medium border-l border-gray-200">HUTANG</td>
                        <td class="px-4 py-3 text-right font-bold">Rp {{ number_format($this->summary['hutang'], 0, ',', '.') }}</td>
                    </tr>
                    <tr class="border-b border-gray-100">
                        <td class="px-4 py-3 font-medium">PELUNASAN HUTANG (TF BCA/QRIS)</td>
                        <td class="px-4 py-3 text-right font-bold">Rp {{ number_format($this->summary['deposit_qris'], 0, ',', '.') }}</td>
                        
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
                                                <span class="block font-semibold text-gray-800">{{ $exp->nama_penghutang ?? 'Tidak ada nama' }}</span>
                                                <span class="text-gray-500 text-[10px] mt-0.5 block">{{ $exp->waktu_transaksi->format('d M Y H:i') }}</span>
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
                    <p class="text-body mb-6">Apakah Anda yakin ingin menghapus data penjualan ini? Stok minuman akan dikembalikan.</p>
                    <div class="flex items-center justify-end gap-3">
                        <button type="button" wire:click="closeDeleteModal" class="px-4 py-2 text-sm font-medium text-body bg-neutral-secondary-medium border border-default-medium rounded-md hover:bg-neutral-secondary-strong transition-colors">
                            Batal
                        </button>
                        <button type="button" wire:click="deleteSale" class="px-4 py-2.5 text-white bg-red-600 hover:bg-red-700 rounded-md font-medium text-sm focus:outline-none">
                            Hapus
                        </button>
                    </div>
                </div>
            </div>
        @endif
    @endif
</div>