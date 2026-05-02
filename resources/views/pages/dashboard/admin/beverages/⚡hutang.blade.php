<?php

namespace App\Livewire\Pages\Dashboard\Admin\Beverages;

use App\Models\BeverageSale;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;

new #[Layout('layouts::admin')] class extends Component
{
    use WithPagination;

    public $searchProduct = '';
    public $start_date = '';
    public $end_date = '';
    public $showConfirmModal = false;
    public $selectedHutangId = null;
    public $selectedKeteranganBayar = '';

    public function mount()
    {
        $this->start_date = date('Y-m-01');
        $this->end_date = date('Y-m-d');
    }

    public function getHutangListProperty()
    {
        return BeverageSale::with(['beverage' => function ($query) {
            $query->withTrashed();
        }])
        ->where('keterangan_bayar', 'hutang')
        ->where('is_lunas', false)
        ->when($this->searchProduct, function ($query) {
            $query->where(function ($q) {
                $q->whereHas('beverage', function ($q2) {
                    $q2->where('nama_produk', 'like', '%' . $this->searchProduct . '%');
                })
                ->orWhere('nama_staff', 'like', '%' . $this->searchProduct . '%')
                ->orWhere('nama_penghutang', 'like', '%' . $this->searchProduct . '%');
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

    public function openConfirmModal($id)
    {
        $sale = BeverageSale::find($id);
        if (!$sale) {
            return;
        }

        $this->selectedHutangId = $id;
        $this->selectedKeteranganBayar = 'deposit_hutang_cash';
        $this->showConfirmModal = true;
    }

    public function confirmLunas()
    {
        if (!$this->selectedHutangId || !$this->selectedKeteranganBayar) {
            return;
        }

        $originalSale = BeverageSale::find($this->selectedHutangId);
        if (!$originalSale) {
            return;
        }

        $totalHarga = $originalSale->total_harga;
        $metodeBayar = $this->selectedKeteranganBayar;

        BeverageSale::create([
            'beverage_id' => $originalSale->beverage_id,
            'nama_produk' => $originalSale->nama_produk,
            'nama_staff' => $originalSale->nama_staff,
            'waktu_transaksi' => now(),
            'shift' => $originalSale->shift,
            'jumlah_beli' => $originalSale->jumlah_beli,
            'harga_satuan' => $originalSale->harga_satuan,
            'total_harga' => $originalSale->total_harga,
            'keterangan_bayar' => $metodeBayar,
            'nama_penghutang' => $originalSale->nama_penghutang,
            'is_lunas' => true,
        ]);

        $originalSale->update(['is_lunas' => true]);

        $this->showConfirmModal = false;
        $this->selectedHutangId = null;
        $this->selectedKeteranganBayar = '';

        session()->flash('success', 'Hutang berhasil dilunasi dengan metode ' . ($metodeBayar === 'deposit_hutang_cash' ? 'Deposit/Cash' : 'Deposit/TF BCA/QRIS') . '.');
    }

    public function deleteHutang($id)
    {
        $sale = BeverageSale::find($id);
        if (!$sale) {
            session()->flash('error', 'Data hutang tidak ditemukan.');
            return;
        }

        $beverage = \App\Models\Beverage::find($sale->beverage_id);
        if ($beverage) {
            $beverage->update([
                'stok_sekarang' => $beverage->stok_sekarang + $sale->jumlah_beli,
            ]);
        }

        $sale->delete();

        session()->flash('success', 'Data hutang berhasil dihapus. Stok minuman dikembalikan.');
    }

    public function with(): array
    {
        return [];
    }
};
?>

<div x-data="{ showConfirmModal: false }">
    <div class="flex sm:flex-row flex-col justify-between items-center mb-6">
        <h5 class="text-xl font-semibold text-heading">Daftar Pelanggan Berhutang</h5>
        <a href="{{ route('admin.beverages.pos') }}"
            class="mt-2 sm:mt-0 px-4 py-2 bg-gray-500 hover:bg-gray-600 text-white text-sm font-semibold rounded-md transition-colors">
            Kembali ke POS
        </a>
    </div>

    @if (session()->has('success'))
        <div x-data='{ show: true }' x-show='show' x-init='setTimeout(() => show = false, 3000)'
            class="p-4 mb-4 text-sm text-green-800 rounded-lg bg-green-50" role="alert">
            <span class="font-medium">Sukses!</span> {{ session('success') }}
        </div>
    @endif

    <div class="bg-neutral-primary-soft shadow-xs rounded-md border border-default">
        <div class="p-4 border-b border-default-medium">
            <div class="flex flex-col sm:flex-row justify-between items-center gap-4">
                <h6 class="text-lg font-semibold text-heading">Daftar Transaksi Hutang</h6>
                <div class="flex flex-col sm:flex-row gap-2 w-full sm:w-auto">
                    <input type="date" wire:model.live="start_date"
                        class="px-2 py-1.5 bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base focus:ring-brand focus:border-brand shadow-xs">
                    <input type="date" wire:model.live="end_date"
                        class="px-2 py-1.5 bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base focus:ring-brand focus:border-brand shadow-xs">
                </div>
            </div>
            <div class="mt-3">
                <input type="text" wire:model.live="searchProduct"
                    class="block w-full px-3 py-2 bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base focus:ring-brand focus:border-brand shadow-xs placeholder:text-body"
                    placeholder="Cari nama produk, staff, atau penghutang...">
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left rtl:text-right text-body">
                <thead class="text-sm text-body bg-neutral-secondary-medium border-b border-default-medium">
                    <tr>
                        <th scope="col" class="px-4 py-3 font-medium">Tanggal</th>
                        <th scope="col" class="px-4 py-3 font-medium">Staff</th>
                        <th scope="col" class="px-4 py-3 font-medium">Nama Penghutang</th>
                        <th scope="col" class="px-4 py-3 font-medium">Produk</th>
                        <th scope="col" class="px-4 py-3 font-medium text-center">Jumlah</th>
                        <th scope="col" class="px-4 py-3 font-medium text-right">Total</th>
                        <th scope="col" class="px-4 py-3 font-medium">Shift</th>
                        <th scope="col" class="px-4 py-3 font-medium text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->hutangList as $sale)
                        <tr class="bg-neutral-primary-soft border-b border-default hover:bg-neutral-secondary-medium">
                            <td class="px-4 py-3 whitespace-nowrap">{{ $sale->waktu_transaksi->format('d M Y H:i') }}</td>
                            <td class="px-4 py-3 whitespace-nowrap">{{ $sale->nama_staff }}</td>
                            <td class="px-4 py-3 whitespace-nowrap font-semibold text-red-600">{{ $sale->nama_penghutang ?? '-' }}</td>
                            <td class="px-4 py-3 whitespace-nowrap">{{ $sale->nama_produk ?? '-' }}</td>
                            <td class="px-4 py-3 text-center whitespace-nowrap">{{ $sale->jumlah_beli }}</td>
                            <td class="px-4 py-3 text-right whitespace-nowrap font-semibold text-emerald-600">Rp {{ number_format($sale->total_harga, 0, ',', '.') }}</td>
                            <td class="px-4 py-3 whitespace-nowrap">{{ ucfirst($sale->shift) }}</td>
                            <td class="px-4 py-3 text-center whitespace-nowrap">
                                <button type="button" wire:click="openConfirmModal({{ $sale->id }})" @click="showConfirmModal = true"
                                    class="px-3 py-1 bg-emerald-500 hover:bg-emerald-600 text-white text-xs font-semibold rounded-md transition-colors">
                                    Bayar Hutang
                                </button>
                                @if(auth()->check() && auth()->user()->role === 'admin')
                                    <button type="button" wire:click="deleteHutang({{ $sale->id }})" wire:confirm="Apakah Anda yakin ingin menghapus hutang ini? Stok minuman akan dikembalikan."
                                        class="px-3 py-1 bg-red-500 hover:bg-red-600 text-white text-xs font-semibold rounded-md transition-colors ml-2">
                                        Hapus
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-8 text-center text-gray-500">Tidak ada data hutang.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4 px-4">
            {{ $this->hutangList->links() }}
        </div>
    </div>

    <div x-show="showConfirmModal"
        x-on:keydown.escape.window="showConfirmModal = false"
        class="fixed inset-0 z-50 overflow-y-auto"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        style="display: none;">
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-black/30 backdrop-blur-sm" x-on:click="showConfirmModal = false"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div x-show="showConfirmModal"
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                x-transition:leave="transition ease-in duration-200"
                x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                class="relative inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform sm:align-middle sm:max-w-md sm:w-full">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="text-center">
                        <h3 class="text-lg leading-6 font-semibold text-heading mb-4">Konfirmasi Pelunasan Hutang</h3>
                        <div class="mb-4">
                            <label class="block mb-2 text-sm font-medium text-heading">Metode Bayar</label>
                            <select wire:model="selectedKeteranganBayar"
                                class="block w-full px-3 py-2 bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base focus:ring-brand focus:border-brand shadow-xs">
                                <option value="deposit_hutang_cash">Deposit/Cash</option>
                                <option value="deposit_hutang_qris">Deposit/TF BCA/QRIS</option>
                            </select>
                        </div>
                        <div class="flex flex-col gap-2">
                            <button type="button" wire:click="confirmLunas" @click="showConfirmModal = false"
                                class="w-full px-4 py-2 bg-emerald-500 hover:bg-emerald-600 text-white text-sm font-semibold rounded-md transition-colors">
                                Ya, Lunaskan
                            </button>
                            <button type="button" @click="showConfirmModal = false"
                                class="w-full px-4 py-2 bg-gray-300 hover:bg-gray-400 text-gray-700 text-sm font-semibold rounded-md transition-colors">
                                Batal
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>