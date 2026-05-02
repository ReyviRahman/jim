<?php

namespace App\Livewire\Pages\Dashboard\Admin\Beverages;

use App\Exports\BeverageStockExport;
use App\Models\Beverage;
use App\Models\BeverageRestock;
use App\Models\BeverageSale;
use App\Models\BeverageStokSnapshot;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;

new #[Layout('layouts::admin')] class extends Component
{
    use WithPagination;

    public $search = '';
    public $start_date = '';
    public $editingStokAwalId = null;
    public $editingStokAwalValue = 0;

    public function mount()
    {
        $this->start_date = date('Y-m-d');
    }

    public function editStokAwal($id)
    {
        $beverage = Beverage::withTrashed()->find($id);
        if (!$beverage) {
            session()->flash('error', 'Produk tidak ditemukan.');
            return;
        }

        $snapshot = BeverageStokSnapshot::where('beverage_id', $id)
            ->where('tipe', 'init')
            ->when($this->start_date, fn($q) => $q->whereDate('tanggal', $this->start_date))
            ->first();

        $this->editingStokAwalId = $id;
        $this->editingStokAwalValue = $snapshot ? $snapshot->jumlah : 0;
    }

    public function saveStokAwal($id)
    {
        $beverage = Beverage::withTrashed()->find($id);
        if (!$beverage) {
            session()->flash('error', 'Produk tidak ditemukan.');
            return;
        }

        $validated = $this->validate([
            'editingStokAwalValue' => 'required|integer|min:0',
        ]);

        $snapshot = BeverageStokSnapshot::where('beverage_id', $id)
            ->where('tipe', 'init')
            ->when($this->start_date, fn($q) => $q->whereDate('tanggal', $this->start_date))
            ->first();

        $stokAwalLama = $snapshot ? $snapshot->jumlah : 0;
        $selisih = $this->editingStokAwalValue - $stokAwalLama;

        $stokSekarangBaru = $beverage->stok_sekarang + $selisih;
        if ($stokSekarangBaru < 0) {
            session()->flash('error', 'Stok tidak boleh negatif. Stok sekarang: ' . $beverage->stok_sekarang);
            return;
        }

        BeverageStokSnapshot::updateOrCreate(
            ['beverage_id' => $id, 'tanggal' => $this->start_date, 'tipe' => 'init'],
            ['jumlah' => $this->editingStokAwalValue]
        );

        $beverage->update([
            'stok_sekarang' => $stokSekarangBaru,
        ]);

        $this->editingStokAwalId = null;
        $this->editingStokAwalValue = 0;

        session()->flash('success', 'Stok awal berhasil diperbarui. Stok sekarang disinkronkan.');
    }

    public function cancelEditStokAwal()
    {
        $this->editingStokAwalId = null;
        $this->editingStokAwalValue = 0;
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function getIsTodayProperty(): bool
    {
        return empty($this->start_date) || $this->start_date === date('Y-m-d');
    }

    public function getBeveragesWithStockProperty()
    {
        $beverages = Beverage::withTrashed()
            ->when($this->search, fn($q) => $q->where('nama_produk', 'like', '%' . $this->search . '%'))
            ->orderBy('nama_produk')
            ->paginate(10);

        foreach ($beverages as $beverage) {
            $beverage->stok_awal = BeverageStokSnapshot::where('beverage_id', $beverage->id)
                ->where('tipe', 'init')
                ->when($this->start_date, fn($q) => $q->whereDate('tanggal', $this->start_date))
                ->sum('jumlah');

            $beverage->ditambahkan = BeverageRestock::where('beverage_id', $beverage->id)
                ->where('tipe', 'restock')
                ->when($this->start_date, fn($q) => $q->whereDate('tanggal', $this->start_date))
                ->sum('jumlah_tambah');

            $beverage->jumlah_stok = $beverage->stok_awal + $beverage->ditambahkan;

            $beverage->terjual = BeverageSale::where('beverage_id', $beverage->id)
                ->whereNotIn('keterangan_bayar', ['deposit_hutang_cash', 'deposit_hutang_qris'])
                ->when($this->start_date, fn($q) => $q->whereDate('waktu_transaksi', $this->start_date))
                ->sum('jumlah_beli');

            $beverage->total_penjualan = $beverage->terjual * $beverage->harga_jual;

            if ($this->isToday) {
                $beverage->stok_akhir = $beverage->stok_sekarang;
            } else {
                $beverage->stok_akhir = BeverageStokSnapshot::where('beverage_id', $beverage->id)
                    ->where('tipe', 'last')
                    ->when($this->start_date, fn($q) => $q->whereDate('tanggal', $this->start_date))
                    ->sum('jumlah');
            }
        }

        return $beverages;
    }

    public function restore($id)
    {
        Beverage::withTrashed()->find($id)->restore();
        session()->flash('success', 'Produk berhasil dikembalikan.');
    }

    public function delete($id)
    {
        Beverage::withTrashed()->find($id)->delete();
        session()->flash('success', 'Produk berhasil dihapus.');
    }

    public function forceDelete($id)
    {
        try {
            Beverage::withTrashed()->find($id)->forceDelete();
            session()->flash('success', 'Produk berhasil dihapus permanen.');
        } catch (\Illuminate\Database\QueryException $e) {
            session()->flash('error', 'Gagal menghapus permanen. Produk masih memiliki data transaksi terkait.');
        }
    }

    public function syncStokAwal($id)
    {
        $beverage = Beverage::find($id);
        if (!$beverage) {
            session()->flash('error', 'Produk tidak ditemukan.');
            return;
        }

        BeverageStokSnapshot::updateOrCreate(
            ['beverage_id' => $id, 'tanggal' => $this->start_date, 'tipe' => 'init'],
            ['jumlah' => $beverage->stok_sekarang]
        );

        session()->flash('success', 'Stok awal berhasil disinkronkan.');
    }

    public function syncStokAkhir($id)
    {
        $beverage = Beverage::find($id);
        if (!$beverage) {
            session()->flash('error', 'Produk tidak ditemukan.');
            return;
        }

        BeverageStokSnapshot::updateOrCreate(
            ['beverage_id' => $id, 'tanggal' => $this->start_date, 'tipe' => 'last'],
            ['jumlah' => $beverage->stok_sekarang]
        );

        session()->flash('success', 'Stok akhir berhasil disinkronkan.');
    }

    public function exportExcel()
    {
        $fileName = 'stok-minuman-' . ($this->start_date ?: date('Y-m-d')) . '.xlsx';

        return Excel::download(
            new BeverageStockExport($this->search, $this->start_date),
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
        <h5 class="text-xl font-semibold text-heading">Stok Minuman</h5>
        <div class="flex gap-2">
            <a href="{{ route('admin.beverages.create') }}" wire:navigate class="text-white bg-brand box-border border border-transparent hover:bg-brand-strong focus:ring-4 focus:ring-brand-medium shadow-xs font-medium leading-5 rounded-md text-sm px-4 py-2.5 focus:outline-none">+ Tambah Produk</a>
            <a href="{{ route('admin.beverages.restock') }}" wire:navigate class="text-heading bg-neutral-secondary-medium border border-default-medium hover:bg-neutral-secondary-strong focus:ring-4 focus:ring-brand-medium shadow-xs font-medium leading-5 rounded-md text-sm px-4 py-2.5 focus:outline-none">Tambah Stock</a>
        </div>
    </div>

    @if (session()->has('success'))
        <div class="p-4 mb-4 text-sm text-green-800 rounded-lg bg-green-50" role="alert">
            <span class="font-medium">Sukses!</span> {{ session('success') }}
        </div>
    @endif

    @if (session()->has('error'))
        <div class="p-4 mb-4 text-sm text-red-800 rounded-lg bg-red-50" role="alert">
            <span class="font-medium">Error!</span> {{ session('error') }}
        </div>
    @endif

    <div class="bg-neutral-primary-soft shadow-xs rounded-md border border-default">
        <div class="p-4 flex flex-col md:flex-row items-center justify-between gap-4">
            <div class="flex flex-col sm:flex-row gap-2 w-full">
                <div class="flex-1">
                    <input type="text" wire:model.live.debounce.300ms="search" class="block w-full ps-9 pe-3 py-2.5 bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base focus:ring-brand focus:border-brand shadow-xs placeholder:text-body" placeholder="Cari nama produk...">
                </div>
                <div class="flex gap-2 items-end">
                    <div>
                        <label class="block mb-1 text-xs font-medium text-heading">Tanggal</label>
                        <input type="date" wire:model.live="start_date"
                            class="block px-2 py-1.5 bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base focus:ring-brand focus:border-brand shadow-xs">
                    </div>
                    <button type="button" wire:click="exportExcel" wire:loading.attr="disabled" class="inline-flex items-center justify-center text-white bg-emerald-600 border border-transparent hover:bg-emerald-700 focus:ring-4 focus:ring-emerald-300 shadow-xs font-medium rounded-md text-sm px-4 py-2.5 focus:outline-none disabled:opacity-50 h-[34px]">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mr-1.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="15" y2="3"/></svg>
                        <span wire:loading.remove wire:target="exportExcel">Export Excel</span>
                        <span wire:loading wire:target="exportExcel">Memproses...</span>
                    </button>
                </div>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left rtl:text-right text-body">
                <thead class="text-sm text-body bg-neutral-secondary-medium border-b border-default-medium">
                    <tr>
                        <th scope="col" class="px-4 py-3 font-medium">No</th>
                        <th scope="col" class="px-4 py-3 font-medium">Produk</th>
                        <th scope="col" class="px-4 py-3 font-medium text-right">Harga Modal</th>
                        <th scope="col" class="px-4 py-3 font-medium text-right">Harga Jual</th>
                        <th scope="col" class="px-4 py-3 font-medium text-center">Stok Awal</th>
                        <th scope="col" class="px-4 py-3 font-medium text-center">Ditambahkan</th>
                        <th scope="col" class="px-4 py-3 font-medium text-center">Jumlah Stok</th>
                        <th scope="col" class="px-4 py-3 font-medium text-center">Terjual</th>
                        <th scope="col" class="px-4 py-3 font-medium text-right">Total Penjualan</th>
                        <th scope="col" class="px-4 py-3 font-medium text-center">Stok Akhir</th>
                        <th scope="col" class="px-4 py-3 font-medium text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->beveragesWithStock as $beverage)
                        <tr wire:key="{{ $beverage->id }}" class="border-b border-default hover:bg-neutral-secondary-medium {{ $beverage->stok_akhir == 0 ? 'bg-red-100' : 'bg-neutral-primary-soft' }}">
                            <td class="px-4 py-3 font-medium text-heading">
                                {{ $loop->iteration + ($this->beveragesWithStock->currentPage() - 1) * $this->beveragesWithStock->perPage() }}
                            </td>
                            <td class="px-4 py-3 font-medium text-heading whitespace-nowrap">
                                {{ $beverage->nama_produk }}
                                @if ($beverage->trashed())
                                    <span class="ml-2 text-xs text-red-600 bg-red-100 px-2 py-0.5 rounded-full">Dihapus</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                Rp {{ number_format($beverage->harga_modal, 0, ',', '.') }}
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                Rp {{ number_format($beverage->harga_jual, 0, ',', '.') }}
                            </td>
                            <td class="px-4 py-3 text-center whitespace-nowrap">
                                @if(auth()->check() && auth()->user()->role === 'admin')
                                    @if ($editingStokAwalId === $beverage->id)
                                        <div class="flex items-center justify-center gap-1">
                                            <input type="number" wire:model="editingStokAwalValue" min="0" class="w-16 px-1 py-0.5 text-sm text-center bg-white border border-default-medium rounded focus:ring-brand focus:border-brand" wire:keydown.enter="saveStokAwal({{ $beverage->id }})">

                                            <button type="button" wire:click="saveStokAwal({{ $beverage->id }})" class="text-emerald-600 hover:text-emerald-800" title="Simpan">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                            </button>

                                            <button type="button" wire:click="cancelEditStokAwal" class="text-red-600 hover:text-red-800" title="Batal">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                                            </button>
                                        </div>
                                    @else
                                        <span>{{ $beverage->stok_awal }}</span>
                                        <button type="button" wire:click="editStokAwal({{ $beverage->id }})" class="ml-1 text-xs text-gray-500 hover:text-brand" title="Edit stok awal">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"></path></svg>
                                        </button>
                                    @endif
                                @else
                                    <span>{{ $beverage->stok_awal }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center whitespace-nowrap text-emerald-600 font-semibold">
                                +{{ $beverage->ditambahkan }}
                            </td>
                            <td class="px-4 py-3 text-center whitespace-nowrap font-semibold text-heading">
                                {{ $beverage->jumlah_stok }}
                            </td>
                            <td class="px-4 py-3 text-center whitespace-nowrap text-red-600 font-semibold">
                                {{ $beverage->terjual }}
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap text-emerald-600 font-semibold">
                                Rp {{ number_format($beverage->total_penjualan, 0, ',', '.') }}
                            </td>
                            <td class="px-4 py-3 text-center whitespace-nowrap">
                                <span class="{{ $beverage->stok_akhir <= 5 ? 'text-red-600 font-bold' : 'text-emerald-600 font-semibold' }}">
                                    {{ $beverage->stok_akhir }}
                                </span>
                                {{-- <button type="button" wire:click="syncStokAkhir({{ $beverage->id }})" wire:confirm="Yakin ingin menyinkronkan stok akhir?" class="ml-1 text-xs text-purple-600 hover:text-purple-800" title="Sinkronkan stok akhir">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 0 0-9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"></path><path d="M3 3v5h5"></path><path d="M3 12a9 9 0 0 0 9 9 9.75 9.75 0 0 0 6.74-2.74L21 16"></path><path d="M16 16h5v5"></path></svg>
                                </button> --}}
                            </td>
                            <td class="px-4 py-3 text-center whitespace-nowrap">
                                <div class="flex items-center justify-center gap-2">
                                    @if(auth()->check() && auth()->user()->role === 'admin')
                                        @if (!$beverage->trashed())
                                            <button type="button" wire:click="delete({{ $beverage->id }})" wire:confirm="Apakah Anda yakin ingin menghapus produk ini?" class="inline-flex items-center gap-1 px-3 py-2 text-sm font-medium text-red-700 bg-red-50 border border-red-200 rounded-md hover:bg-red-100 focus:ring-2 focus:ring-red-300 transition-colors">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"></path><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path><line x1="10" x2="10" y1="11" y2="17"></line><line x1="14" x2="14" y1="11" y2="17"></line></svg>
                                            </button>
                                        @endif
                                        <a href="{{ route('admin.beverages.edit', ['beverage' => $beverage->id]) }}" wire:navigate class="inline-flex items-center gap-1 px-3 py-2 text-sm font-medium text-yellow-700 bg-yellow-50 border border-yellow-200 rounded-md hover:bg-yellow-100 focus:ring-2 focus:ring-yellow-300 transition-colors">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"></path></svg>
                                        </a>
                                        @if ($beverage->trashed())
                                            <button type="button" wire:click="restore({{ $beverage->id }})" wire:confirm="Apakah Anda yakin ingin mengembalikan produk ini?" class="inline-flex items-center gap-1 px-3 py-2 text-sm font-medium text-blue-700 bg-blue-50 border border-blue-200 rounded-md hover:bg-blue-100 focus:ring-2 focus:ring-blue-300 transition-colors">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"></path><path d="M21 3v5h-5"></path><path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"></path><path d="M3 21v-5h5"></path></svg>
                                            </button>
                                            <button type="button" wire:click="forceDelete({{ $beverage->id }})" wire:confirm="Apakah Anda yakin ingin menghapus permanen produk ini?" class="inline-flex items-center gap-1 px-3 py-2 text-sm font-medium text-red-700 bg-red-50 border border-red-200 rounded-md hover:bg-red-100 focus:ring-2 focus:ring-red-300 transition-colors">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"></path><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path></svg>
                                            </button>
                                        @endif
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11" class="px-4 py-8 text-center text-gray-500">
                                Belum ada data minuman.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4 px-4">
            {{ $this->beveragesWithStock->links() }}
        </div>
    </div>
</div>