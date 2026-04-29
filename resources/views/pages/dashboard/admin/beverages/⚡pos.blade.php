<?php

namespace App\Livewire\Pages\Dashboard\Admin\Beverages;

use App\Models\Beverage;
use App\Models\BeverageSale;
use Livewire\Component;
use Livewire\Attributes\Layout;

new #[Layout('layouts::admin')] class extends Component
{
    public $searchProduct = '';
    public $selectedProducts = [];
    public $shift = 'pagi';
    public $keterangan_bayar = 'cash';

    public $nama_staff = '';

    public function mount()
    {
        $this->nama_staff = auth()->user()->name ?? '';
    }

    public function selectProduct($id)
    {
        $beverage = Beverage::find($id);
        if (!$beverage || $beverage->stok_sekarang <= 0) {
            return;
        }

        $exists = collect($this->selectedProducts)->firstWhere('beverage_id', $id);
        if ($exists) {
            return;
        }

        $this->selectedProducts[] = [
            'beverage_id' => $id,
            'nama_produk' => $beverage->nama_produk,
            'harga_satuan' => $beverage->harga_jual,
            'jumlah_beli' => 1,
            'stok_tersedia' => $beverage->stok_sekarang,
        ];
        $this->searchProduct = '';
    }

    public function removeProduct($index)
    {
        array_splice($this->selectedProducts, $index, 1);
    }

    public function updateQuantity($index, $change)
    {
        $currentQty = $this->selectedProducts[$index]['jumlah_beli'];
        $maxStok = $this->selectedProducts[$index]['stok_tersedia'];
        $newQty = $currentQty + $change;

        if ($newQty < 1) {
            return;
        }
        if ($newQty > $maxStok) {
            return;
        }

        $this->selectedProducts[$index]['jumlah_beli'] = $newQty;
    }

    public function getProductsProperty()
    {
        if (strlen($this->searchProduct) < 1) {
            return collect();
        }
        return Beverage::where('stok_sekarang', '>', 0)
            ->where('nama_produk', 'like', '%' . $this->searchProduct . '%')
            ->orderBy('nama_produk')
            ->limit(10)
            ->get();
    }

    public function getTotalProperty()
    {
        return collect($this->selectedProducts)->sum(function ($item) {
            return $item['harga_satuan'] * $item['jumlah_beli'];
        });
    }

    public function getTotalItemsProperty()
    {
        return collect($this->selectedProducts)->sum('jumlah_beli');
    }

    public function processSale()
    {
        if (empty($this->selectedProducts)) {
            session()->flash('error', 'Pilih produk terlebih dahulu.');
            return;
        }

        if (empty($this->nama_staff)) {
            session()->flash('error', 'Nama staff harus diisi.');
            return;
        }

        $now = now();

        foreach ($this->selectedProducts as $item) {
            BeverageSale::create([
                'beverage_id' => $item['beverage_id'],
                'nama_staff' => $this->nama_staff,
                'waktu_transaksi' => $now,
                'shift' => $this->shift,
                'jumlah_beli' => $item['jumlah_beli'],
                'harga_satuan' => $item['harga_satuan'],
                'total_harga' => $item['harga_satuan'] * $item['jumlah_beli'],
                'keterangan_bayar' => $this->keterangan_bayar,
            ]);

            $beverage = Beverage::find($item['beverage_id']);
            $beverage->update([
                'stok_sekarang' => $beverage->stok_sekarang - $item['jumlah_beli'],
            ]);
        }

        session()->flash('success', 'Transaksi berhasil disimpan! Total: Rp ' . number_format($this->total, 0, ',', '.'));

        $this->selectedProducts = [];
        $this->shift = 'pagi';
        $this->keterangan_bayar = 'cash';
    }

    public function with(): array
    {
        return [];
    }
};
?>

<div>
    <div class="flex sm:flex-row flex-col justify-between items-center mb-6">
        <h5 class="text-xl font-semibold text-heading">POS Minuman</h5>
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

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 bg-neutral-primary-soft shadow-xs rounded-md border border-default">
            <div class="p-4 border-b border-default-medium">
                <h6 class="text-lg font-semibold text-heading mb-3">Pilih Produk</h6>
                <div class="relative">
                    <input type="text" wire:model.live="searchProduct"
                        class="block w-full px-3 py-2.5 bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base focus:ring-brand focus:border-brand shadow-xs placeholder:text-body"
                        placeholder="Ketik nama produk...">
                    @if ($this->products->count() > 0)
                        <div class="absolute z-10 w-full mt-1 bg-white border border-default-medium rounded-md shadow-lg max-h-60 overflow-y-auto">
                            @foreach ($this->products as $product)
                                <button type="button" wire:click="selectProduct({{ $product->id }})"
                                    class="w-full px-4 py-3 text-left hover:bg-neutral-secondary-medium transition-colors border-b border-default last:border-b-0">
                                    <div class="flex justify-between items-center">
                                        <div>
                                            <div class="font-semibold text-heading">{{ $product->nama_produk }}</div>
                                            <div class="text-xs text-gray-500">Rp {{ number_format($product->harga_jual, 0, ',', '.') }}</div>
                                        </div>
                                        <div class="text-right">
                                            <span class="text-xs text-emerald-600 font-semibold">Stok: {{ $product->stok_sekarang }}</span>
                                        </div>
                                    </div>
                                </button>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            <div class="p-4">
                @if (count($this->selectedProducts) > 0)
                    <table class="w-full text-sm text-left text-body">
                        <thead class="text-sm bg-neutral-secondary-medium border-b border-default-medium">
                            <tr>
                                <th class="px-2 py-2 font-medium">Produk</th>
                                <th class="px-2 py-2 font-medium text-right">Harga</th>
                                <th class="px-2 py-2 font-medium text-center">Jumlah</th>
                                <th class="px-2 py-2 font-medium text-right">Subtotal</th>
                                <th class="px-2 py-2 font-medium"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($this->selectedProducts as $index => $item)
                                <tr class="border-b border-default">
                                    <td class="px-2 py-2 font-semibold text-heading">{{ $item['nama_produk'] }}</td>
                                    <td class="px-2 py-2 text-right">Rp {{ number_format($item['harga_satuan'], 0, ',', '.') }}</td>
                                    <td class="px-2 py-2 text-center">
                                        <div class="flex items-center justify-center gap-2">
                                            <button type="button" wire:click="updateQuantity({{ $index }}, -1)" class="w-7 h-7 rounded-full bg-neutral-secondary-medium hover:bg-neutral-tertiary-medium text-heading font-bold">-</button>
                                            <span class="w-8 text-center font-semibold">{{ $item['jumlah_beli'] }}</span>
                                            <button type="button" wire:click="updateQuantity({{ $index }}, 1)" class="w-7 h-7 rounded-full bg-neutral-secondary-medium hover:bg-neutral-tertiary-medium text-heading font-bold">+</button>
                                        </div>
                                    </td>
                                    <td class="px-2 py-2 text-right font-semibold text-heading">Rp {{ number_format($item['harga_satuan'] * $item['jumlah_beli'], 0, ',', '.') }}</td>
                                    <td class="px-2 py-2 text-center">
                                        <button type="button" wire:click="removeProduct({{ $index }})" class="text-red-500 hover:text-red-700">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <div class="text-center py-12 text-gray-500">
                        <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" class="mx-auto mb-3 opacity-50"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" x2="21" y1="6" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
                        <p>Belum ada produk yang dipilih</p>
                    </div>
                @endif
            </div>
        </div>

        <div class="bg-neutral-primary-soft shadow-xs rounded-md border border-default p-6">
            <h6 class="text-lg font-semibold text-heading mb-4">Ringkasan Pembelian</h6>

            <div class="mb-4">
                <label class="block mb-2 text-sm font-medium text-heading">Nama Staff</label>
                <input type="text" wire:model="nama_staff"
                    class="block w-full px-3 py-2.5 bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base focus:ring-brand focus:border-brand shadow-xs">
            </div>

            <div class="mb-4">
                <label class="block mb-2 text-sm font-medium text-heading">Shift</label>
                <select wire:model="shift" class="block w-full px-3 py-2.5 bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base focus:ring-brand focus:border-brand shadow-xs">
                    <option value="pagi">Pagi</option>
                    <option value="siang">Siang</option>
                </select>
            </div>

            <div class="mb-4">
                <label class="block mb-2 text-sm font-medium text-heading">Metode Bayar</label>
                <select wire:model="keterangan_bayar" class="block w-full px-3 py-2.5 bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base focus:ring-brand focus:border-brand shadow-xs">
                    <option value="cash">Cash</option>
                    <option value="qris">QRIS</option>
                    <option value="tf_bca">Transfer BCA</option>
                    <option value="lunas">Lunas</option>
                    <option value="deposit_hutang">Deposit/Hutang</option>
                    <option value="belum_bayar">Belum Bayar</option>
                </select>
            </div>

            <div class="border-t border-default-medium pt-4 mt-4">
                <div class="flex justify-between items-center mb-2">
                    <span class="text-body">Total Item</span>
                    <span class="font-semibold text-heading">{{ $this->totalItems }} item</span>
                </div>
                <div class="flex justify-between items-center mb-4">
                    <span class="text-lg font-semibold text-heading">Total Bayar</span>
                    <span class="text-xl font-bold text-emerald-600">Rp {{ number_format($this->total, 0, ',', '.') }}</span>
                </div>
            </div>

            <button type="button" wire:click="processSale" @if(count($this->selectedProducts) === 0) disabled @endif
                class="w-full py-3 text-white bg-brand hover:bg-brand-strong rounded-md font-semibold text-sm focus:outline-none disabled:opacity-50 disabled:cursor-not-allowed">
                Bayar Sekarang
            </button>
        </div>
    </div>
</div>