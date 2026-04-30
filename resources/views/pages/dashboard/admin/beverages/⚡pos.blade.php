<?php

namespace App\Livewire\Pages\Dashboard\Admin\Beverages;

use App\Models\Beverage;
use App\Models\BeverageSale;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\Request;

new #[Layout('layouts::admin')] class extends Component
{
    public $searchProduct = '';
    public $selectedProducts = [];
    public $shift = '';
    public $keterangan_bayar = 'cash';
    public $nama_staff = '';
    public $products = [];
    public $showNotFound = false;

    public function mount()
    {
        $this->nama_staff = auth()->user()->name ?? '';
        $this->shift = auth()->user()->shift ?? 'pagi';
    }

    public function searchProducts()
    {
        if (strlen($this->searchProduct) < 1) {
            $this->products = [];
            $this->showNotFound = false;
            return;
        }

        $this->products = Beverage::where('stok_sekarang', '>', 0)
            ->where('nama_produk', 'like', '%' . $this->searchProduct . '%')
            ->orderBy('nama_produk')
            ->limit(10)
            ->get()
            ->toArray();

        $this->showNotFound = count($this->products) === 0;
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
        $this->products = [];
        $this->showNotFound = false;
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

        if ($newQty < 1 || $newQty > $maxStok) {
            return;
        }

        $this->selectedProducts[$index]['jumlah_beli'] = $newQty;
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
            return redirect(request()->header('Referer'));
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
        $this->shift = auth()->user()->shift ?? 'pagi';
        $this->keterangan_bayar = 'cash';

        return redirect(request()->header('Referer'));
    }

    public function with(): array
    {
        return [];
    }
};
?>

<div x-data="{
    searchProduct: '',
    selectedProducts: [],
    products: [],
    showNotFound: false,
    keterangan_bayar: 'cash',
    total: 0,
    totalItems: 0,
    showModal: false,
    shift: '{{ auth()->user()->shift ?? 'pagi' }}',
    nama_staff: '{{ auth()->user()->name ?? '' }}',
    tanggal: (() => {
                        const d = new Date();
                        const year = d.getFullYear();
                        const month = String(d.getMonth() + 1).padStart(2, '0');
                        const day = String(d.getDate()).padStart(2, '0');
                        return `${year}-${month}-${day}`;
                    })(),

    async searchProducts() {
        if (this.searchProduct.length < 1) {
            this.products = [];
            this.showNotFound = false;
            return;
        }
        const response = await fetch(`/api/beverages/search?q=${encodeURIComponent(this.searchProduct)}`);
        this.products = await response.json();
        this.showNotFound = this.products.length === 0;
    },

    selectProduct(id) {
        const product = this.products.find(p => p.id === id);
        if (!product || product.stok_sekarang <= 0) return;
        if (this.selectedProducts.find(p => p.beverage_id === id)) return;

        this.selectedProducts.push({
            beverage_id: product.id,
            nama_produk: product.nama_produk,
            harga_satuan: product.harga_jual,
            jumlah_beli: 1,
            stok_tersedia: product.stok_sekarang
        });
        this.searchProduct = '';
        this.products = [];
        this.showNotFound = false;
        this.updateTotals();
    },

    removeProduct(index) {
        this.selectedProducts.splice(index, 1);
        this.updateTotals();
    },

    updateQuantity(index, change) {
        const currentQty = this.selectedProducts[index].jumlah_beli;
        const maxStok = this.selectedProducts[index].stok_tersedia;
        const newQty = currentQty + change;
        if (newQty < 1 || newQty > maxStok) return;
        this.selectedProducts[index].jumlah_beli = newQty;
        this.updateTotals();
    },

    updateTotals() {
        this.total = this.selectedProducts.reduce((sum, item) => sum + (item.harga_satuan * item.jumlah_beli), 0);
        this.totalItems = this.selectedProducts.reduce((sum, item) => sum + item.jumlah_beli, 0);
    },

    formatNumber(num) {
        return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    },

    openModal() {
        if (this.selectedProducts.length === 0) return;
        this.showModal = true;
    },

    closeModal() {
        this.showModal = false;
    },

    submitForm() {
        document.getElementById('pos-form').submit();
    }
}">
    <div class="flex sm:flex-row flex-col justify-between items-center mb-6">
        <h5 class="text-xl font-semibold text-heading">POS Minuman</h5>
    </div>

    @if (session()->has('success'))
        <div x-data='{ show: true }' x-show='show' x-init='setTimeout(() => show = false, 3000)'
            class="p-4 mb-4 text-sm text-green-800 rounded-lg bg-green-50" role="alert">
            <span class="font-medium">Sukses!</span> {{ session('success') }}
        </div>
    @endif
    @if (session()->has('error'))
        <div x-data='{ show: true }' x-show='show' x-init='setTimeout(() => show = false, 3000)'
            class="p-4 mb-4 text-sm text-red-800 rounded-lg bg-red-50" role="alert">
            <span class="font-medium">Gagal!</span> {{ session('error') }}
        </div>
    @endif

    <form id="pos-form" action="{{ route('admin.beverages.pos.process') }}" method="POST">
        @csrf
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 bg-neutral-primary-soft shadow-xs rounded-md border border-default">
                <div class="p-4 border-b border-default-medium">
                    <h6 class="text-lg font-semibold text-heading mb-3">Pilih Produk</h6>
                    <div class="relative">
                        <input type="text" x-model="searchProduct" @input.debounce.300ms="searchProducts()"
                            class="block w-full px-3 py-2.5 bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base focus:ring-brand focus:border-brand shadow-xs placeholder:text-body"
                            placeholder="Ketik nama produk... (Le Minerale)">
                        <template x-if="products.length > 0">
                            <div class="absolute z-10 w-full mt-1 bg-white border border-default-medium rounded-md shadow-lg max-h-60 overflow-y-auto">
                                <template x-for="product in products" :key="product.id">
                                    <button type="button" @click="selectProduct(product.id)"
                                        class="w-full px-4 py-3 text-left hover:bg-neutral-secondary-medium transition-colors border-b border-default last:border-b-0">
                                        <div class="flex justify-between items-center">
                                            <div>
                                                <div class="font-semibold text-heading" x-text="product.nama_produk"></div>
                                                <div class="text-xs text-gray-500" x-text="'Rp ' + formatNumber(product.harga_jual)"></div>
                                            </div>
                                            <div class="text-right">
                                                <span class="text-xs text-emerald-600 font-semibold" x-text="'Stok: ' + product.stok_sekarang"></span>
                                            </div>
                                        </div>
                                    </button>
                                </template>
                            </div>
                        </template>
                        <template x-if="showNotFound && searchProduct.length >= 1">
                            <div class="absolute z-10 w-full mt-1 bg-white border border-default-medium rounded-md shadow-lg p-3 text-sm text-gray-500">
                                Produk <span x-text='\"\'' + searchProduct + '\"'</span> tidak ditemukan.
                            </div>
                        </template>
                    </div>
                </div>

                <div class="p-4">
                    <template x-if="selectedProducts.length > 0">
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
                                <template x-for="(item, index) in selectedProducts" :key="item.beverage_id">
                                    <tr class="border-b border-default">
                                        <td class="px-2 py-2 font-semibold text-heading" x-text="item.nama_produk"></td>
                                        <td class="px-2 py-2 text-right" x-text="'Rp ' + formatNumber(item.harga_satuan)"></td>
                                        <td class="px-2 py-2 text-center">
                                            <div class="flex items-center justify-center gap-2">
                                                <button type="button" @click="updateQuantity(index, -1)" class="w-7 h-7 rounded-full bg-neutral-secondary-medium hover:bg-neutral-tertiary-medium text-heading font-bold">-</button>
                                                <span class="w-8 text-center font-semibold" x-text="item.jumlah_beli"></span>
                                                <button type="button" @click="updateQuantity(index, 1)" class="w-7 h-7 rounded-full bg-neutral-secondary-medium hover:bg-neutral-tertiary-medium text-heading font-bold">+</button>
                                            </div>
                                        </td>
                                        <td class="px-2 py-2 text-right font-semibold text-heading" x-text="'Rp ' + formatNumber(item.harga_satuan * item.jumlah_beli)"></td>
                                        <td class="px-2 py-2 text-center">
                                            <button type="button" @click="removeProduct(index)" class="text-red-500 hover:text-red-700">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                                            </button>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </template>
                    <template x-if="selectedProducts.length === 0">
                        <div class="text-center py-12 text-gray-500">
                            <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" class="mx-auto mb-3 opacity-50"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" x2="21" y1="6" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
                            <p>Ketik Nama Produk Pada Kolom di atas</p>
                        </div>
                    </template>
                </div>
            </div>

            <div class="bg-neutral-primary-soft shadow-xs rounded-md border border-default p-6">
                <h6 class="text-lg font-semibold text-heading mb-4">Ringkasan Pembelian</h6>

                <div class="mb-4">
                    <label class="block mb-2 text-sm font-medium text-heading">Nama Staff</label>
                    <input type="text" x-model="nama_staff" name="nama_staff"
                        class="block w-full px-3 py-2.5 bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base shadow-xs" readonly>
                </div>

                <div class="mb-4">
                    <label class="block mb-2 text-sm font-medium text-heading">Shift</label>
                    <input type="text" x-model="shift" name="shift"
                        class="block w-full px-3 py-2.5 bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base shadow-xs" readonly>
                </div>

                <div class="mb-4">
                    <label class="block mb-2 text-sm font-medium text-heading">Tanggal</label>
                    <input type="date" x-model="tanggal" name="tanggal"
                        class="block w-full px-3 py-2.5 bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base focus:ring-brand focus:border-brand shadow-xs">
                </div>

                <div class="mb-4">
                    <label class="block mb-2 text-sm font-medium text-heading">Metode Bayar</label>
                    <select x-model="keterangan_bayar" name="keterangan_bayar" 
                        class="block w-full px-3 py-2.5 bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base focus:ring-brand focus:border-brand shadow-xs">
                        <option value="cash">Cash</option>
                        <option value="qris">QRIS</option>
                        <option value="tf_bca">Transfer BCA</option>
                        <option value="deposit_hutang">Deposit/Hutang</option>
                        <option value="hutang">Hutang</option>
                    </select>
                </div>

                <div class="border-t border-default-medium pt-4 mt-4">
                    <div class="flex justify-between items-center mb-2">
                        <span class="text-body">Total Item</span>
                        <span class="font-semibold text-heading" x-text="totalItems + ' item'"></span>
                    </div>
                    <div class="flex justify-between items-center mb-4">
                        <span class="text-lg font-semibold text-heading">Total Bayar</span>
                        <span class="text-xl font-bold text-emerald-600" x-text="'Rp ' + formatNumber(total)"></span>
                    </div>
                </div>

                <input type="hidden" name="selected_products" :value="JSON.stringify(selectedProducts)">

                <button type="button" @click="openModal()" :disabled="selectedProducts.length === 0"
                    class="w-full py-3 text-white bg-brand hover:bg-brand-strong rounded-md font-semibold text-sm focus:outline-none disabled:opacity-50 disabled:cursor-not-allowed">
                    Bayar Sekarang
                </button>
            </div>
        </div>
    </form>

    <div x-show="showModal" x-on:keydown.escape.window="closeModal()"
        class="fixed inset-0 z-50 overflow-y-auto"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        style="display: none;">
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-black/30 backdrop-blur-sm" @click="closeModal()"></div>

            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

            <div x-show="showModal"
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                x-transition:leave="transition ease-in duration-200"
                x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                class="relative inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform sm:align-middle sm:max-w-lg sm:w-full">

                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="mt-3 text-center sm:mt-0 sm:text-left w-full">
                            <h3 class="text-lg leading-6 font-semibold text-heading mb-4">Konfirmasi Pembayaran</h3>
                            
                            <div class="mb-4 p-3 bg-neutral-secondary-medium rounded-md">
                                <div class="grid grid-cols-2 gap-2 text-sm">
                                    <div>
                                        <span class="text-body">Staff:</span>
                                        <span class="font-semibold text-heading" x-text="nama_staff"></span>
                                    </div>
                                    <div>
                                        <span class="text-body">Shift:</span>
                                        <span class="font-semibold text-heading" x-text="shift.charAt(0).toUpperCase() + shift.slice(1)"></span>
                                    </div>
                                    <div>
                                        <span class="text-body">Metode:</span>
                                        <span class="font-semibold text-heading" x-text="keterangan_bayar.charAt(0).toUpperCase() + keterangan_bayar.slice(1).replace('_', ' ')"></span>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-4">
                                <h4 class="font-semibold text-heading mb-2">Daftar Belanja:</h4>
                                <div class="max-h-48 overflow-y-auto border border-default-medium rounded-md">
                                    <table class="w-full text-sm">
                                        <thead class="bg-neutral-secondary-medium sticky top-0">
                                            <tr>
                                                <th class="px-2 py-1 text-left font-medium text-heading">Produk</th>
                                                <th class="px-2 py-1 text-center font-medium text-heading">Qty</th>
                                                <th class="px-2 py-1 text-right font-medium text-heading">Subtotal</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <template x-for="item in selectedProducts" :key="item.beverage_id">
                                                <tr class="border-b border-default">
                                                    <td class="px-2 py-1.5 text-heading" x-text="item.nama_produk"></td>
                                                    <td class="px-2 py-1.5 text-center text-heading" x-text="item.jumlah_beli"></td>
                                                    <td class="px-2 py-1.5 text-right text-heading" x-text="'Rp ' + formatNumber(item.harga_satuan * item.jumlah_beli)"></td>
                                                </tr>
                                            </template>
                                        </tbody>
                                        <tfoot class="bg-neutral-secondary-medium">
                                            <tr>
                                                <td colspan="2" class="px-2 py-1.5 font-semibold text-heading">Total</td>
                                                <td class="px-2 py-1.5 text-right font-bold text-emerald-600" x-text="'Rp ' + formatNumber(total)"></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="button" @click="submitForm()"
                        class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-brand text-base font-medium text-white hover:bg-brand-strong focus:outline-none sm:ml-3 sm:w-auto sm:text-sm">
                        Konfirmasi Bayar
                    </button>
                    <button type="button" @click="closeModal()"
                        class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-heading hover:bg-gray-50 focus:outline-none sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                        Batal
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>