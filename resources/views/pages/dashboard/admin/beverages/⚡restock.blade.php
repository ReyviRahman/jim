<?php

namespace App\Livewire\Pages\Dashboard\Admin\Beverages;

use App\Models\Beverage;
use App\Models\BeverageRestock;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;

new #[Layout('layouts::admin')] class extends Component
{
    use WithPagination;

    public $searchProduct = '';

    public $beverage_id = '';
    public $selectedBeverage = null;
    public $jumlah_tambah = '';
    public $keterangan = '';
    public $tanggal = '';
    public $jadikan_stok_awal = false;

    public $editingId = null;
    public $edit_jumlah_tambah = '';
    public $edit_keterangan = '';
    public $edit_tanggal = '';

    public $deleteId = null;

    public $start_date = '';
    public $end_date = '';

    public function mount()
    {
        $this->tanggal = date('Y-m-d');
        $this->start_date = date('Y-m-01');
        $this->end_date = date('Y-m-d');
    }

    public function selectProduct($id)
    {
        $this->beverage_id = $id;
        $this->selectedBeverage = Beverage::find($id);
        $this->searchProduct = '';
    }

    public function clearProduct()
    {
        $this->beverage_id = '';
        $this->selectedBeverage = null;
    }

    public function store()
    {
        $this->validate([
            'beverage_id' => 'required',
            'jumlah_tambah' => 'required|integer|min:1',
        ]);

        $beverage = Beverage::find($this->beverage_id);
        if (!$beverage) {
            session()->flash('error', 'Produk tidak ditemukan.');
            return;
        }

        $newStokSekarang = $beverage->stok_sekarang + $this->jumlah_tambah;

        BeverageRestock::create([
            'beverage_id' => $this->beverage_id,
            'tanggal' => $this->tanggal,
            'jumlah_tambah' => $this->jumlah_tambah,
            'keterangan' => $this->keterangan,
        ]);

        $updateData = [
            'stok_sekarang' => $newStokSekarang,
        ];

        if ($this->jadikan_stok_awal) {
            $updateData['stok_awal'] = $this->jumlah_tambah;
        }

        $beverage->update($updateData);

        session()->flash('success', 'Stok berhasil ditambahkan.');

        $this->reset(['beverage_id', 'jumlah_tambah', 'keterangan', 'selectedBeverage', 'jadikan_stok_awal']);
        $this->tanggal = date('Y-m-d');
    }

    public function executeDelete()
    {
        if (!$this->deleteId) return;

        $restock = BeverageRestock::find($this->deleteId);
        if ($restock) {
            $beverage = Beverage::find($restock->beverage_id);
            if ($beverage) {
                $beverage->update([
                    'stok_sekarang' => $beverage->stok_sekarang - $restock->jumlah_tambah,
                ]);
            }
            $restock->forceDelete();
            session()->flash('success', 'Data restock berhasil dihapus permanen.');
        }
        $this->deleteId = null;
    }

    public function edit($id)
    {
        $restock = BeverageRestock::find($id);
        if ($restock) {
            $this->editingId = $id;
            $this->edit_tanggal = $restock->tanggal->format('Y-m-d');
            $this->edit_jumlah_tambah = $restock->jumlah_tambah;
            $this->edit_keterangan = $restock->keterangan ?? '';
        }
    }

    public function cancelEdit()
    {
        $this->editingId = null;
        $this->edit_jumlah_tambah = '';
        $this->edit_keterangan = '';
        $this->edit_tanggal = '';
    }

    public function confirmDelete($id)
    {
        $this->deleteId = $id;
    }

    public function cancelDelete()
    {
        $this->deleteId = null;
    }

    public function update()
    {
        $this->validate([
            'editingId' => 'required',
            'edit_jumlah_tambah' => 'required|integer|min:1',
        ]);

        $restock = BeverageRestock::find($this->editingId);
        if (!$restock) {
            session()->flash('error', 'Data restock tidak ditemukan.');
            return;
        }

        $selisih = $this->edit_jumlah_tambah - $restock->jumlah_tambah;

        $restock->update([
            'tanggal' => $this->edit_tanggal,
            'jumlah_tambah' => $this->edit_jumlah_tambah,
            'keterangan' => $this->edit_keterangan,
        ]);

        $beverage = Beverage::find($restock->beverage_id);
        if ($beverage) {
            $beverage->update([
                'stok_sekarang' => $beverage->stok_sekarang + $selisih,
            ]);
        }

        session()->flash('success', 'Data restock berhasil diperbarui.');

        $this->cancelEdit();
    }

    public function getProductsProperty()
    {
        if (strlen($this->searchProduct) < 1) {
            return collect();
        }
        return Beverage::where('nama_produk', 'like', '%' . $this->searchProduct . '%')
            ->orderBy('nama_produk')
            ->limit(10)
            ->get();
    }

    public function getRestocksProperty()
    {
        return BeverageRestock::with(['beverage' => function ($query) {
            $query->withTrashed();
        }])
        ->when($this->start_date, function ($query) {
            $query->whereDate('tanggal', '>=', $this->start_date);
        })
        ->when($this->end_date, function ($query) {
            $query->whereDate('tanggal', '<=', $this->end_date);
        })
        ->latest()
        ->paginate(10);
    }

    public function with(): array
    {
        return [];
    }
};
?>

<div>
    <div class="flex sm:flex-row flex-col justify-between items-center mb-6">
        <h5 class="text-xl font-semibold text-heading">Tambah Stok Minuman</h5>
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

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-neutral-primary-soft shadow-xs rounded-md border border-default p-6">
            <h6 class="text-lg font-semibold text-heading mb-4">Form Tambah Stok</h6>
            <form wire:submit.prevent="store">
                <div class="mb-4">
                    <label for="tanggal" class="block mb-2.5 text-sm font-medium text-heading">Tanggal</label>
                    <input type="date" id="tanggal" wire:model="tanggal"
                        class="block w-full px-3 py-2.5 bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base focus:ring-brand focus:border-brand shadow-xs">
                </div>

                <div class="mb-4">
                    <label class="block mb-2.5 text-sm font-medium text-heading">Pilih Produk</label>
                    @if ($this->selectedBeverage)
                        <div class="flex items-center gap-3 p-3 bg-emerald-50 border border-emerald-200 rounded-md">
                            <div class="flex-1">
                                <div class="font-semibold text-heading">{{ $this->selectedBeverage->nama_produk }}</div>
                                <div class="text-sm text-gray-500">Stok saat ini: <span class="font-semibold text-emerald-600">{{ $this->selectedBeverage->stok_sekarang }}</span></div>
                            </div>
                            <button type="button" wire:click="clearProduct" class="text-red-500 hover:text-red-700">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"></path><path d="m6 6 12 12"></path></svg>
                            </button>
                        </div>
                    @else
                        <div class="relative">
                            <input type="text" wire:model.live="searchProduct"
                                class="block w-full px-3 py-2.5 bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base focus:ring-brand focus:border-brand shadow-xs placeholder:text-body"
                                placeholder="Ketik nama produk...">
                            @if ($this->products->count() > 0)
                                <div class="absolute z-10 w-full mt-1 bg-white border border-default-medium rounded-md shadow-lg max-h-60 overflow-y-auto">
                                    @foreach ($this->products as $product)
                                        <button type="button" wire:click="selectProduct({{ $product->id }})"
                                            class="w-full px-4 py-2 text-left hover:bg-neutral-secondary-medium transition-colors border-b border-default last:border-b-0">
                                            <div class="font-semibold text-heading">{{ $product->nama_produk }}</div>
                                            <div class="text-xs text-gray-500">Stok: {{ $product->stok_sekarang }}</div>
                                        </button>
                                    @endforeach
                                </div>
                            @elseif (strlen($this->searchProduct) >= 1)
                                <div class="absolute z-10 w-full mt-1 bg-white border border-default-medium rounded-md shadow-lg p-3 text-sm text-gray-500">
                                    Produk "{{ $this->searchProduct }}" tidak ditemukan.
                                </div>
                            @endif
                        </div>
                    @endif
                    @error('beverage_id') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                </div>

                <div class="mb-4">
                    <label for="jumlah_tambah" class="block mb-2.5 text-sm font-medium text-heading">Jumlah Tambah</label>
                    <input type="text" 
                        inputmode="numeric" 
                        pattern="[0-9]*" 
                        id="jumlah_tambah" 
                        wire:model.live="jumlah_tambah" 
                        class="block w-full px-3 py-2.5 bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base focus:ring-brand focus:border-brand shadow-xs placeholder:text-body"
                        placeholder="Contoh: 10">
                    @error('jumlah_tambah') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                </div>

                <div class="mb-4">
                    <label for="keterangan" class="block mb-2.5 text-sm font-medium text-heading">Keterangan (Opsional)</label>
                    <input type="text" id="keterangan" wire:model="keterangan"
                        class="block w-full px-3 py-2.5 bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base focus:ring-brand focus:border-brand shadow-xs placeholder:text-body"
                        placeholder="Contoh: Pengiriman dari supplier">
                </div>

                <div class="flex items-center gap-3">
            <a href="{{ route('admin.beverages.index') }}" wire:navigate class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-body bg-neutral-secondary-medium border border-default-medium rounded-md hover:bg-neutral-secondary-strong transition-colors">
                Batal
            </a>
            <button type="submit" class="px-4 py-2.5 text-white bg-brand hover:bg-brand-strong rounded-md font-medium text-sm focus:outline-none">
                Simpan Stok
            </button>
        </div>
            </form>
        </div>

        <div class="bg-neutral-primary-soft shadow-xs rounded-md border border-default">
            <div class="p-4 border-b border-default-medium">
                <div class="flex flex-col gap-3">
                    <h6 class="text-lg font-semibold text-heading">Riwayat Restock</h6>
                    <div class="flex gap-2">
                        <div class="flex-1">
                            <label class="block mb-1 text-xs font-medium text-heading">Tanggal Mulai</label>
                            <input type="date" wire:model.live="start_date"
                                class="block w-full px-2 py-1.5 bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base focus:ring-brand focus:border-brand shadow-xs">
                        </div>
                        <div class="flex-1">
                            <label class="block mb-1 text-xs font-medium text-heading">Tanggal Akhir</label>
                            <input type="date" wire:model.live="end_date"
                                class="block w-full px-2 py-1.5 bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base focus:ring-brand focus:border-brand shadow-xs">
                        </div>
                    </div>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left rtl:text-right text-body">
                    <thead class="text-sm text-body bg-neutral-secondary-medium border-b border-default-medium">
                        <tr>
                            <th scope="col" class="px-4 py-3 font-medium">Tanggal</th>
                            <th scope="col" class="px-4 py-3 font-medium">Produk</th>
                            <th scope="col" class="px-4 py-3 font-medium text-center">Jumlah</th>
                            <th scope="col" class="px-4 py-3 font-medium">Keterangan</th>
                            <th scope="col" class="px-4 py-3 font-medium text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->restocks as $restock)
                            <tr wire:key="{{ $restock->id }}" class="bg-neutral-primary-soft border-b border-default hover:bg-neutral-secondary-medium">
                                @if ($restock->id === $this->editingId)
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <input type="date" wire:model.live="edit_tanggal" class="block w-full px-2 py-1 bg-white border border-default-medium text-heading text-xs rounded-base">
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">{{ $restock->beverage->nama_produk ?? '-' }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <input type="text" inputmode="numeric" pattern="[0-9]*" wire:model.live="edit_jumlah_tambah" class="block w-20 px-2 py-1 bg-white border border-default-medium text-heading text-xs rounded-base text-center">
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <input type="text" wire:model.live="edit_keterangan" class="block w-full px-2 py-1 bg-white border border-default-medium text-heading text-xs rounded-base" placeholder="Keterangan">
                                    </td>
                                @else
                                    <td class="px-4 py-3 whitespace-nowrap">{{ $restock->tanggal->format('d M Y') }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap">{{ $restock->beverage->nama_produk ?? '-' }}</td>
                                    <td class="px-4 py-3 text-center whitespace-nowrap text-emerald-600 font-semibold">+{{ $restock->jumlah_tambah }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap text-xs">{{ $restock->keterangan ?? '-' }}</td>
                                @endif
                                <td class="px-4 py-3 text-center whitespace-nowrap">
                                    @if ($restock->id === $this->editingId)
                                        <div class="flex items-center justify-center gap-1">
                                            <button type="button" wire:click="update" class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium text-white bg-emerald-600 border border-emerald-600 rounded-md hover:bg-emerald-700">
                                                Simpan
                                            </button>
                                            <button type="button" wire:click="cancelEdit" class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium text-gray-700 bg-gray-100 border border-gray-300 rounded-md hover:bg-gray-200">
                                                Batal
                                            </button>
                                        </div>
                                    @elseif ($this->deleteId === $restock->id)
                                        <div class="flex items-center justify-center gap-1">
                                            <span class="text-xs text-red-600 font-medium">Hapus?</span>
                                            <button type="button" wire:click="executeDelete" class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium text-white bg-red-600 border border-red-600 rounded-md hover:bg-red-700">
                                                Ya
                                            </button>
                                            <button type="button" wire:click="cancelDelete" class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium text-gray-700 bg-gray-100 border border-gray-300 rounded-md hover:bg-gray-200">
                                                Tidak
                                            </button>
                                        </div>
                                    @else
                                        <div class="flex items-center justify-center gap-1">
                                            <button type="button" wire:click="edit({{ $restock->id }})" class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium text-blue-700 bg-blue-50 border border-blue-200 rounded-md hover:bg-blue-100">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/><path d="m15 5 4 4"/></svg>
                                                Edit
                                            </button>
                                            <button type="button" wire:click="confirmDelete({{ $restock->id }})" class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium text-red-700 bg-red-50 border border-red-200 rounded-md hover:bg-red-100 focus:ring-2 focus:ring-red-300 transition-colors">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/></svg>
                                                Hapus
                                            </button>
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-8 text-center text-gray-500">Belum ada riwayat restock.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4 px-4">
                {{ $this->restocks->links() }}
            </div>
        </div>
    </div>
</div>