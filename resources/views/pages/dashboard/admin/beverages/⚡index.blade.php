<?php

namespace App\Livewire\Pages\Dashboard\Admin\Beverages;

use App\Models\Beverage;
use App\Models\BeverageRestock;
use App\Models\BeverageSale;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;

new #[Layout('layouts::admin')] class extends Component
{
    use WithPagination;

    public $search = '';

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function getBeveragesWithStockProperty()
    {
        $beverages = Beverage::withTrashed()
            ->when($this->search, fn($q) => $q->where('nama_produk', 'like', '%' . $this->search . '%'))
            ->orderBy('nama_produk')
            ->paginate(10);

        foreach ($beverages as $beverage) {
            $totalRestock = BeverageRestock::where('beverage_id', $beverage->id)->sum('jumlah_tambah');
            $beverage->ditambahkan = max(0, $totalRestock - $beverage->stok_awal);
            $beverage->terjual = BeverageSale::where('beverage_id', $beverage->id)->sum('jumlah_beli');
            $beverage->stok_akhir = $beverage->stok_awal + $beverage->ditambahkan - $beverage->terjual;
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
        Beverage::withTrashed()->find($id)->forceDelete();
        session()->flash('success', 'Produk berhasil dihapus permanen.');
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
            <a href="{{ route('admin.beverages.create') }}" wire:navigate class="text-white bg-brand box-border border border-transparent hover:bg-brand-strong focus:ring-4 focus:ring-brand-medium shadow-xs font-medium leading-5 rounded-md text-sm px-4 py-2.5 focus:outline-none">+ Tambah Minuman</a>
        </div>
    </div>

    @if (session()->has('success'))
        <div class="p-4 mb-4 text-sm text-green-800 rounded-lg bg-green-50" role="alert">
            <span class="font-medium">Sukses!</span> {{ session('success') }}
        </div>
    @endif

    <div class="bg-neutral-primary-soft shadow-xs rounded-md border border-default">
        <div class="p-4 flex flex-col md:flex-row items-center justify-between gap-4">
            <div class="relative w-full md:w-auto md:flex-1">
                <div class="absolute inset-y-0 start-0 flex items-center ps-3 pointer-events-none">
                    <svg class="w-4 h-4 text-body" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-width="2" d="m21 21-3.5-3.5M17 10a7 7 0 1 1-14 0 7 7 0 0 1 14 0Z"/></svg>
                </div>
                <input type="text" wire:model.live.debounce.300ms="search" class="block w-full max-w-sm ps-9 pe-3 py-2.5 bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base focus:ring-brand focus:border-brand shadow-xs placeholder:text-body" placeholder="Cari nama produk...">
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
                        <th scope="col" class="px-4 py-3 font-medium text-center">Terjual</th>
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
                                {{ $beverage->stok_awal }}
                            </td>
                            <td class="px-4 py-3 text-center whitespace-nowrap text-emerald-600 font-semibold">
                                +{{ $beverage->ditambahkan }}
                            </td>
                            <td class="px-4 py-3 text-center whitespace-nowrap text-red-600 font-semibold">
                                -{{ $beverage->terjual }}
                            </td>
                            <td class="px-4 py-3 text-center whitespace-nowrap">
                                <span class="{{ $beverage->stok_akhir <= 5 ? 'text-red-600 font-bold' : 'text-emerald-600 font-semibold' }}">
                                    {{ $beverage->stok_akhir }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center whitespace-nowrap">
                                <div class="flex items-center justify-center gap-2">
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
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-4 py-8 text-center text-gray-500">
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