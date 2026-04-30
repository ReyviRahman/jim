<?php

namespace App\Livewire\Pages\Dashboard\Admin\Beverages;

use App\Exports\BeverageSaleExport;
use App\Models\BeverageSale;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;

new #[Layout('layouts::admin')] class extends Component
{
    use WithPagination;

    public $searchProduct = '';
    public $start_date = '';
    public $end_date = '';

    public function mount()
    {
        $this->start_date = date('Y-m-01');
        $this->end_date = date('Y-m-d');
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
            $query->whereDate('waktu_transaksi', '>=', $this->start_date);
        })
        ->when($this->end_date, function ($query) {
            $query->whereDate('waktu_transaksi', '<=', $this->end_date);
        })
        ->latest()
        ->paginate(10);
    }

    public function exportExcel()
    {
        $fileName = 'penjualan_minuman_' . date('Y-m-d_His') . '.xlsx';

        return Excel::download(
            new BeverageSaleExport(
                $this->searchProduct,
                $this->start_date,
                $this->end_date
            ),
            $fileName
        );
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
                    <input type="date" wire:model.live="end_date"
                        class="px-2 py-1.5 bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base focus:ring-brand focus:border-brand shadow-xs">
                    <button type="button" wire:click="exportExcel"
                        class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white text-sm rounded-base font-medium inline-flex items-center gap-1">
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
                        <th scope="col" class="px-4 py-3 font-medium">Produk</th>
                        <th scope="col" class="px-4 py-3 font-medium text-center">Jumlah</th>
                        <th scope="col" class="px-4 py-3 font-medium text-right">Harga Satuan</th>
                        <th scope="col" class="px-4 py-3 font-medium text-right">Total</th>
                        <th scope="col" class="px-4 py-3 font-medium">Shift</th>
                        <th scope="col" class="px-4 py-3 font-medium">Metode Bayar</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->sales as $sale)
                        <tr class="bg-neutral-primary-soft border-b border-default hover:bg-neutral-secondary-medium">
                            <td class="px-4 py-3 whitespace-nowrap">{{ $sale->waktu_transaksi->format('d M Y H:i') }}</td>
                            <td class="px-4 py-3 whitespace-nowrap">{{ $sale->nama_staff }}</td>
                            <td class="px-4 py-3 whitespace-nowrap">{{ $sale->beverage->nama_produk ?? '-' }}</td>
                            <td class="px-4 py-3 text-center whitespace-nowrap">{{ $sale->jumlah_beli }}</td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">Rp {{ number_format($sale->harga_satuan, 0, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right whitespace-nowrap font-semibold text-emerald-600">Rp {{ number_format($sale->total_harga, 0, ',', '.') }}</td>
                            <td class="px-4 py-3 whitespace-nowrap">{{ ucfirst($sale->shift) }}</td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                @php
                                    $metode = [
                                        'cash' => 'Cash',
                                        'deposit_hutang' => 'Deposit/bayar utang',
                                        'tf_bca_qris' => 'TF BCA/Qris',
                                        'pengeluaran_umum' => 'Pengeluaran Umum',
                                        'hutang' => 'Hutang',
                                        'operasional' => 'Operasional',
                                    ];
                                @endphp
                                {{ $metode[$sale->keterangan_bayar] ?? $sale->keterangan_bayar }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-8 text-center text-gray-500">Belum ada riwayat penjualan.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4 px-4">
            {{ $this->sales->links() }}
        </div>
    </div>
</div>