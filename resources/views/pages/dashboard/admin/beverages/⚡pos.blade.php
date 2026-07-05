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
    public $nama_penghutang = '';
    public $cash_received = '';
    public $nama_staff = '';
    public $products = [];
    public $showNotFound = false;
    public $showExpenseModal = false;
    public $expense_name = '';
    public $expense_amount = '';
    public $expense_date = '';

    public function mount()
    {
        $this->nama_staff = auth()->user()->name ?? '';
        $this->shift = auth()->user()->shift ?? 'pagi';
        $this->expense_date = now()->format('Y-m-d');
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

    public function openExpenseModal()
    {
        $this->showExpenseModal = true;
    }

    public function closeExpenseModal()
    {
        $this->showExpenseModal = false;
        $this->expense_name = '';
        $this->expense_amount = '';
        $this->expense_date = now()->format('Y-m-d');
    }

    public function saveExpense()
    {
        if (empty($this->expense_name) || empty($this->expense_amount)) {
            session()->flash('error', 'Nama dan jumlah pengeluaran harus diisi.');
            return;
        }

        BeverageSale::create([
            'nama_staff' => $this->nama_staff,
            'waktu_transaksi' => $this->expense_date,
            'shift' => $this->shift,
            'jumlah_beli' => 0,
            'harga_satuan' => 0,
            'total_harga' => (int) str_replace('.', '', $this->expense_amount),
            'keterangan_bayar' => 'pengeluaran_umum',
            'nama_produk' => $this->expense_name,
            'nama_penghutang' => auth()->user()->name,
        ]);

        session()->flash('success', 'Pengeluaran berhasil disimpan!');

        $this->closeExpenseModal();
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
    nama_penghutang: '',
    cash_received: '',
    change_amount: 0,
    save_change_as_deposit: false,
    deposit_customer_name: '',
    deposits: [],
    depositSearchQuery: '',
    selected_deposit_id: '',
    selected_deposit_balance: 0,
    selected_deposit_name: '',
    secondary_payment_method: '',
    secondary_cash_received: '',
    secondary_change_amount: 0,
    secondary_nama_penghutang: '',
    save_secondary_change_as_deposit: false,
    secondary_deposit_customer_name: '',
    total: 0,
    totalItems: 0,
    showModal: false,
    showExpenseModal: false,
    expense_name: '',
    expense_amount: '',
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

    async loadDeposits(query = '') {
        const url = '/api/beverages/deposits' + (query ? `?q=${encodeURIComponent(query)}` : '');
        const response = await fetch(url);
        this.deposits = await response.json();
    },

    searchDeposits() {
        this.loadDeposits(this.depositSearchQuery);
    },

    selectDeposit(deposit) {
        this.selected_deposit_id = deposit.id;
        this.selected_deposit_balance = deposit.sisa_nominal;
        this.selected_deposit_name = deposit.nama_pelanggan;
        this.depositSearchQuery = deposit.nama_pelanggan + ' (Rp ' + this.formatNumber(deposit.sisa_nominal) + ')';
        this.deposits = [];
        this.secondary_payment_method = '';
        this.secondary_cash_received = '';
        this.secondary_change_amount = 0;
        this.secondary_nama_penghutang = '';
    },

    clearSelectedDeposit() {
        this.selected_deposit_id = '';
        this.selected_deposit_balance = 0;
        this.selected_deposit_name = '';
        this.depositSearchQuery = '';
        this.deposits = [];
        this.secondary_payment_method = '';
        this.secondary_cash_received = '';
        this.secondary_change_amount = 0;
        this.secondary_nama_penghutang = '';
    },

    handlePaymentMethodChange(event) {
        const method = event.target.value;
        if (method !== 'cash') {
            this.cash_received = '';
            if (this.$refs.cash_input) {
                this.$refs.cash_input.value = '';
            }
            this.save_change_as_deposit = false;
            this.deposit_customer_name = '';
            this.updateChangeAmount();
        }
        if (method === 'deposit') {
            this.loadDeposits();
        } else {
            this.selected_deposit_id = '';
            this.selected_deposit_balance = 0;
            this.selected_deposit_name = '';
            this.depositSearchQuery = '';
            this.deposits = [];
            this.secondary_payment_method = '';
            this.secondary_cash_received = '';
            this.secondary_change_amount = 0;
            this.secondary_nama_penghutang = '';
        }
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
        this.updateChangeAmount();
        this.updateSecondaryChangeAmount();
    },

    getRemainingTotal() {
        if (this.keterangan_bayar === 'deposit' && this.selected_deposit_id) {
            return Math.max(0, this.total - this.selected_deposit_balance);
        }
        return 0;
    },

    handleSecondaryPaymentMethodChange(event) {
        const method = event.target.value;
        if (method !== 'cash') {
            this.secondary_cash_received = '';
            if (this.$refs.secondary_cash_input) {
                this.$refs.secondary_cash_input.value = '';
            }
            this.save_secondary_change_as_deposit = false;
            this.secondary_deposit_customer_name = '';
            this.updateSecondaryChangeAmount();
        }
        if (method !== 'hutang') {
            this.secondary_nama_penghutang = '';
        }
    },

    formatSecondaryCashInput(event) {
        let value = event.target.value.replace(/\D/g, '');
        this.secondary_cash_received = value;
        event.target.value = value ? this.formatNumber(parseInt(value, 10)) : '';
        this.updateSecondaryChangeAmount();
    },

    updateSecondaryChangeAmount() {
        const cash = parseInt(this.secondary_cash_received, 10) || 0;
        this.secondary_change_amount = Math.max(0, cash - this.getRemainingTotal());
    },

    formatCashInput(event) {
        let value = event.target.value.replace(/\D/g, '');
        this.cash_received = value;
        event.target.value = value ? this.formatNumber(parseInt(value, 10)) : '';
        this.updateChangeAmount();
    },

    updateChangeAmount() {
        const cash = parseInt(this.cash_received, 10) || 0;
        this.change_amount = Math.max(0, cash - this.total);
    },

    formatNumber(num) {
        return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    },

    getFinalTotal() {
        if (this.keterangan_bayar === 'deposit' && this.selected_deposit_id) {
            return Math.max(0, this.total - this.selected_deposit_balance);
        }
        return this.total;
    },

    getDepositUsed() {
        if (this.keterangan_bayar === 'deposit' && this.selected_deposit_id) {
            return Math.min(this.total, this.selected_deposit_balance);
        }
        return 0;
    },

    getDepositRemaining() {
        if (this.keterangan_bayar === 'deposit' && this.selected_deposit_id) {
            return Math.max(0, this.selected_deposit_balance - this.total);
        }
        return 0;
    },

    openModal() {
        if (this.selectedProducts.length === 0) return;
        if (this.keterangan_bayar === 'hutang' && !this.nama_penghutang.trim()) {
            alert('Nama penghutang harus diisi untuk transaksi hutang.');
            return;
        }
        if (this.keterangan_bayar === 'cash') {
            const cash = parseInt(this.cash_received, 10) || 0;
            if (cash < this.total) {
                alert('Nominal cash yang diterima harus lebih besar atau sama dengan total belanja.');
                return;
            }
            if (this.save_change_as_deposit && !this.deposit_customer_name.trim()) {
                alert('Nama pelanggan harus diisi jika kembalian disimpan sebagai deposit.');
                return;
            }
        }
        if (this.keterangan_bayar === 'deposit') {
            if (!this.selected_deposit_id) {
                alert('Pilih deposit yang akan digunakan.');
                return;
            }
            const remaining = this.getRemainingTotal();
            if (remaining > 0) {
                if (!this.secondary_payment_method) {
                    alert('Pilih metode pembayaran untuk sisa total bayar.');
                    return;
                }
                if (this.secondary_payment_method === 'cash') {
                    const cash = parseInt(this.secondary_cash_received, 10) || 0;
                    if (cash < remaining) {
                        alert('Nominal cash untuk sisa pembayaran harus lebih besar atau sama dengan Rp ' + this.formatNumber(remaining) + '.');
                        return;
                    }
                }
                if (this.secondary_payment_method === 'hutang' && !this.secondary_nama_penghutang.trim()) {
                    alert('Nama penghutang harus diisi untuk sisa pembayaran hutang.');
                    return;
                }
                if (this.secondary_payment_method === 'cash' && this.save_secondary_change_as_deposit && !this.secondary_deposit_customer_name.trim()) {
                    alert('Nama pelanggan harus diisi jika kembalian sisa disimpan sebagai deposit.');
                    return;
                }
            }
        }
        this.showModal = true;
    },

    closeModal() {
        this.showModal = false;
        this.save_change_as_deposit = false;
        this.deposit_customer_name = '';
        this.save_secondary_change_as_deposit = false;
        this.secondary_deposit_customer_name = '';
    },

    openExpenseModal() {
        this.showExpenseModal = true;
    },

    closeExpenseModal() {
        this.showExpenseModal = false;
        this.expense_name = '';
        this.expense_amount = '';
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
                    <div class="flex flex-col sm:flex-row justify-between items-center gap-4">
                        <h6 class="text-lg font-semibold text-heading mb-3">Pilih Produk</h6>
                        <div class="flex gap-2">
                            <button type="button" wire:click="openExpenseModal"
                                class="px-4 py-2 bg-red-500 hover:bg-red-600 text-white text-sm font-semibold rounded-md transition-colors">
                                + Pengeluaran Umum
                            </button>
                            <a href="{{ route('admin.beverages.hutang') }}"
                                class="px-4 py-2 bg-orange-500 hover:bg-orange-600 text-white text-sm font-semibold rounded-md transition-colors">
                                Daftar Hutang
                            </a>
                            <a href="{{ route('admin.beverages.deposit') }}"
                                class="px-4 py-2 bg-purple-500 hover:bg-purple-600 text-white text-sm font-semibold rounded-md transition-colors">
                                Daftar Deposit
                            </a>
                        </div>
                    </div>
                    <div class="relative mt-2">
                        <input type="text" x-model="searchProduct" @input.debounce.300ms="searchProducts()"
                            class="block w-full px-3 py-2.5 bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base focus:ring-brand focus:border-brand shadow-xs placeholder:text-body"
                            placeholder="Ketik nama produk... (Crystalin)">
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
                    <select x-model="keterangan_bayar" name="keterangan_bayar" @change="handlePaymentMethodChange($event)"
                        class="block w-full px-3 py-2.5 bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base focus:ring-brand focus:border-brand shadow-xs">
                        <option value="cash">Cash</option>
                        <option value="tf_bca_qris">TF BCA/Qris</option>
                        <option value="hutang">Hutang</option>
                        <option value="operasional">Operasional</option>
                        <option value="deposit">Deposit</option>
                    </select>
                </div>

                <template x-if="keterangan_bayar === 'hutang'">
                    <div class="mb-4">
                        <label class="block mb-2 text-sm font-medium text-heading">Nama Penghutang</label>
                        <input type="text" x-model="nama_penghutang" name="nama_penghutang"
                            class="block w-full px-3 py-2.5 bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base focus:ring-brand focus:border-brand shadow-xs"
                            placeholder="Masukkan nama penghutang">
                    </div>
                </template>

                <template x-if="keterangan_bayar === 'cash'">
                    <div class="mb-4">
                        <label class="block mb-2 text-sm font-medium text-heading">Nominal Cash Diterima</label>
                        <input type="text" x-ref="cash_input" name="cash_received" @input="formatCashInput($event)"
                            class="block w-full px-3 py-2.5 bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base focus:ring-brand focus:border-brand shadow-xs"
                            placeholder="Masukkan nominal cash">
                    </div>
                </template>

                <template x-if="keterangan_bayar === 'deposit'">
                    <div class="mb-4">
                        <label class="block mb-2 text-sm font-medium text-heading">Pilih Deposit</label>
                        <div class="relative">
                            <input type="text" x-model="depositSearchQuery" @input.debounce.300ms="searchDeposits()"
                                class="block w-full px-3 py-2.5 bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base focus:ring-brand focus:border-brand shadow-xs"
                                placeholder="Ketik nama pelanggan...">
                            <template x-if="selected_deposit_id">
                                <button type="button" @click="clearSelectedDeposit()"
                                    class="absolute right-2 top-1/2 -translate-y-1/2 text-red-500 hover:text-red-700">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                                </button>
                            </template>
                        </div>
                        <template x-if="deposits.length > 0">
                            <div class="absolute z-10 w-full mt-1 bg-white border border-default-medium rounded-md shadow-lg max-h-60 overflow-y-auto">
                                <template x-for="deposit in deposits" :key="deposit.id">
                                    <button type="button" @click="selectDeposit(deposit)"
                                        class="w-full px-4 py-3 text-left hover:bg-neutral-secondary-medium transition-colors border-b border-default last:border-b-0">
                                        <div class="flex justify-between items-center">
                                            <span class="font-semibold text-heading" x-text="deposit.nama_pelanggan"></span>
                                            <span class="text-sm text-emerald-600 font-semibold" x-text="'Rp ' + formatNumber(deposit.sisa_nominal)"></span>
                                        </div>
                                    </button>
                                </template>
                            </div>
                        </template>
                        <template x-if="deposits.length === 0 && depositSearchQuery.length >= 1 && !selected_deposit_id">
                            <div class="mt-1 text-sm text-gray-500">Deposit tidak ditemukan.</div>
                        </template>
                        <template x-if="selected_deposit_id">
                            <p class="mt-2 text-sm text-body">
                                Sisa deposit: <span class="font-semibold text-heading" x-text="'Rp ' + formatNumber(selected_deposit_balance)"></span>
                            </p>
                        </template>

                        <template x-if="selected_deposit_id && getRemainingTotal() > 0">
                            <div class="mt-4 p-3 bg-purple-50 rounded-md border border-purple-100">
                                <div class="mb-3">
                                    <label class="block mb-2 text-sm font-medium text-heading">Sisa yang Harus Dibayar</label>
                                    <p class="text-lg font-bold text-purple-700" x-text="'Rp ' + formatNumber(getRemainingTotal())"></p>
                                </div>
                                <div class="mb-3">
                                    <label class="block mb-2 text-sm font-medium text-heading">Metode Pembayaran Sisa</label>
                                    <select x-model="secondary_payment_method" name="secondary_payment_method" @change="handleSecondaryPaymentMethodChange($event)"
                                        class="block w-full px-3 py-2.5 bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base focus:ring-brand focus:border-brand shadow-xs">
                                        <option value="">Pilih Metode</option>
                                        <option value="cash">Cash</option>
                                        <option value="tf_bca_qris">TF BCA/Qris</option>
                                        <option value="hutang">Hutang</option>
                                        <option value="operasional">Operasional</option>
                                    </select>
                                </div>
                                <template x-if="secondary_payment_method === 'cash'">
                                    <div class="mb-3">
                                        <label class="block mb-2 text-sm font-medium text-heading">Nominal Cash Diterima</label>
                                        <input type="text" x-ref="secondary_cash_input" name="secondary_cash_received" @input="formatSecondaryCashInput($event)"
                                            class="block w-full px-3 py-2.5 bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base focus:ring-brand focus:border-brand shadow-xs"
                                            placeholder="Masukkan nominal cash">
                                    </div>
                                </template>
                                <template x-if="secondary_payment_method === 'hutang'">
                                    <div class="mb-3">
                                        <label class="block mb-2 text-sm font-medium text-heading">Nama Penghutang</label>
                                        <input type="text" x-model="secondary_nama_penghutang" name="secondary_nama_penghutang"
                                            class="block w-full px-3 py-2.5 bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base focus:ring-brand focus:border-brand shadow-xs"
                                            placeholder="Masukkan nama penghutang">
                                    </div>
                                </template>
                            </div>
                        </template>

                        <input type="hidden" name="selected_deposit_id" :value="selected_deposit_id">
                    </div>
                </template>

                <div class="border-t border-default-medium pt-4 mt-4">
                    <div class="flex justify-between items-center mb-2">
                        <span class="text-body">Total Item</span>
                        <span class="font-semibold text-heading" x-text="totalItems + ' item'"></span>
                    </div>
                    <div class="flex justify-between items-center mb-2">
                        <span class="text-body">Total Belanja</span>
                        <span class="font-semibold text-heading" x-text="'Rp ' + formatNumber(total)"></span>
                    </div>
                    <template x-if="keterangan_bayar === 'deposit' && selected_deposit_id">
                        <div>
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-body">Deposit Terpakai</span>
                                <span class="font-semibold text-heading" x-text="'Rp ' + formatNumber(getDepositUsed())"></span>
                            </div>
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-body">Sisa Deposit</span>
                                <span class="font-semibold text-heading" x-text="'Rp ' + formatNumber(getDepositRemaining())"></span>
                            </div>
                        </div>
                    </template>
                    <div class="flex justify-between items-center mb-4 pt-2 border-t border-default-medium">
                        <span class="text-lg font-semibold text-heading">Total Bayar</span>
                        <span class="text-xl font-bold text-emerald-600" x-text="'Rp ' + formatNumber(getFinalTotal())"></span>
                    </div>
                    <template x-if="keterangan_bayar === 'cash' && cash_received">
                        <div class="mb-4">
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-body">Kembalian</span>
                                <span class="text-lg font-bold text-blue-600" x-text="'Rp ' + formatNumber(change_amount)"></span>
                            </div>
                            <template x-if="change_amount > 0">
                                <div class="p-3 bg-yellow-50 rounded-md">
                                    <label class="flex items-center gap-2 mb-3 cursor-pointer">
                                        <input type="checkbox" name="save_change_as_deposit" value="1" x-model="save_change_as_deposit"
                                            class="w-4 h-4 text-brand border-default-medium rounded focus:ring-brand">
                                        <span class="text-sm font-medium text-heading">Simpan kembalian sebagai deposit</span>
                                    </label>
                                    <template x-if="save_change_as_deposit">
                                        <div>
                                            <label class="block mb-1 text-sm font-medium text-heading">Nama Pelanggan</label>
                                            <input type="text" name="deposit_customer_name" x-model="deposit_customer_name"
                                                class="block w-full px-3 py-2 bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base focus:ring-brand focus:border-brand shadow-xs"
                                                placeholder="Masukkan nama pelanggan">
                                        </div>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </template>
                    <template x-if="keterangan_bayar === 'deposit' && selected_deposit_id && secondary_payment_method">
                        <div class="flex justify-between items-center mb-2">
                            <span class="text-body">Metode Sisa</span>
                            <span class="font-semibold text-heading" x-text="secondary_payment_method === 'tf_bca_qris' ? 'TF BCA/Qris' : secondary_payment_method.charAt(0).toUpperCase() + secondary_payment_method.slice(1)"></span>
                        </div>
                    </template>
                    <template x-if="keterangan_bayar === 'deposit' && selected_deposit_id && secondary_payment_method === 'cash' && secondary_cash_received">
                        <div class="mb-4">
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-body">Kembalian Sisa</span>
                                <span class="text-lg font-bold text-blue-600" x-text="'Rp ' + formatNumber(secondary_change_amount)"></span>
                            </div>
                            <template x-if="secondary_change_amount > 0">
                                <div class="p-3 bg-yellow-50 rounded-md">
                                    <label class="flex items-center gap-2 mb-3 cursor-pointer">
                                        <input type="checkbox" name="save_secondary_change_as_deposit" value="1" x-model="save_secondary_change_as_deposit"
                                            class="w-4 h-4 text-brand border-default-medium rounded focus:ring-brand">
                                        <span class="text-sm font-medium text-heading">Simpan kembalian sisa sebagai deposit</span>
                                    </label>
                                    <template x-if="save_secondary_change_as_deposit">
                                        <div>
                                            <label class="block mb-1 text-sm font-medium text-heading">Nama Pelanggan</label>
                                            <input type="text" name="secondary_deposit_customer_name" x-model="secondary_deposit_customer_name"
                                                class="block w-full px-3 py-2 bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base focus:ring-brand focus:border-brand shadow-xs"
                                                placeholder="Masukkan nama pelanggan">
                                        </div>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>

                <input type="hidden" name="nama_penghutang" :value="nama_penghutang">
                <input type="hidden" name="secondary_payment_method" :value="secondary_payment_method">
                <input type="hidden" name="secondary_cash_received" :value="secondary_cash_received">
                <input type="hidden" name="secondary_nama_penghutang" :value="secondary_nama_penghutang">
                <input type="hidden" name="save_secondary_change_as_deposit" :value="save_secondary_change_as_deposit ? 1 : 0">
                <input type="hidden" name="secondary_deposit_customer_name" :value="secondary_deposit_customer_name">
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

                            <template x-if="keterangan_bayar === 'cash'">
                                <div class="mb-4 p-3 bg-blue-50 rounded-md">
                                    <div class="grid grid-cols-2 gap-2 text-sm">
                                        <div>
                                            <span class="text-body">Cash Diterima:</span>
                                            <span class="font-semibold text-heading" x-text="'Rp ' + formatNumber(parseInt(cash_received, 10) || 0)"></span>
                                        </div>
                                        <div>
                                            <span class="text-body">Kembalian:</span>
                                            <span class="font-semibold text-heading" x-text="'Rp ' + formatNumber(change_amount)"></span>
                                        </div>
                                    </div>
                                </div>
                            </template>

                            <template x-if="keterangan_bayar === 'deposit' && selected_deposit_id">
                                <div class="mb-4 p-3 bg-purple-50 rounded-md">
                                    <div class="grid grid-cols-2 gap-2 text-sm">
                                        <div>
                                            <span class="text-body">Pelanggan:</span>
                                            <span class="font-semibold text-heading" x-text="selected_deposit_name || '-'"></span>
                                        </div>
                                        <div>
                                            <span class="text-body">Sisa Deposit:</span>
                                            <span class="font-semibold text-heading" x-text="'Rp ' + formatNumber(selected_deposit_balance)"></span>
                                        </div>
                                        <div>
                                            <span class="text-body">Total Belanja:</span>
                                            <span class="font-semibold text-heading" x-text="'Rp ' + formatNumber(total)"></span>
                                        </div>
                                        <div>
                                            <span class="text-body">Deposit Terpakai:</span>
                                            <span class="font-semibold text-heading" x-text="'Rp ' + formatNumber(getDepositUsed())"></span>
                                        </div>
                                        <div>
                                            <span class="text-body">Total Bayar:</span>
                                            <span class="font-semibold text-heading" x-text="'Rp ' + formatNumber(getFinalTotal())"></span>
                                        </div>
                                        <div>
                                            <span class="text-body">Sisa Setelah Bayar:</span>
                                            <span class="font-semibold text-heading" x-text="'Rp ' + formatNumber(getDepositRemaining())"></span>
                                        </div>
                                        <template x-if="getRemainingTotal() > 0">
                                            <div>
                                                <span class="text-body">Metode Sisa:</span>
                                                <span class="font-semibold text-heading" x-text="secondary_payment_method === 'tf_bca_qris' ? 'TF BCA/Qris' : (secondary_payment_method ? secondary_payment_method.charAt(0).toUpperCase() + secondary_payment_method.slice(1) : '-')"></span>
                                            </div>
                                        </template>
                                        <template x-if="getRemainingTotal() > 0 && secondary_payment_method === 'cash'">
                                            <div>
                                                <span class="text-body">Cash Sisa:</span>
                                                <span class="font-semibold text-heading" x-text="'Rp ' + formatNumber(parseInt(secondary_cash_received, 10) || 0)"></span>
                                            </div>
                                        </template>
                                        <template x-if="getRemainingTotal() > 0 && secondary_payment_method === 'cash' && secondary_change_amount > 0">
                                            <div>
                                                <span class="text-body">Kembalian Sisa:</span>
                                                <span class="font-semibold text-heading" x-text="'Rp ' + formatNumber(secondary_change_amount)"></span>
                                            </div>
                                        </template>
                                        <template x-if="getRemainingTotal() > 0 && secondary_payment_method === 'cash' && secondary_change_amount > 0 && save_secondary_change_as_deposit">
                                            <div class="col-span-2">
                                                <span class="text-body">Simpan Kembalian sebagai Deposit:</span>
                                                <span class="font-semibold text-heading" x-text="'Ya (' + secondary_deposit_customer_name + ')'"></span>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </template>

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

    <div x-show="showExpenseModal" x-on:keydown.escape.window="closeExpenseModal()"
        class="fixed inset-0 z-50 overflow-y-auto"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        style="display: none;">
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-black/30 backdrop-blur-sm" @click="closeExpenseModal()"></div>

            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

            <div x-show="showExpenseModal"
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                x-transition:leave="transition ease-in duration-200"
                x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                class="relative inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform sm:align-middle sm:max-w-md sm:w-full">

                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="text-center">
                        <h3 class="text-lg leading-6 font-semibold text-heading mb-4">Tambah Pengeluaran Umum</h3>
                        
                        <div class="mb-4">
                            <label class="block mb-2 text-sm font-medium text-heading">Nama Pengeluaran</label>
                            <input type="text" wire:model.live="expense_name"
                                class="block w-full px-3 py-2.5 bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base focus:ring-brand focus:border-brand shadow-xs"
                                placeholder="Contoh: Listrik, Air, Maintenance">
                        </div>

                        <div class="mb-4">
                            <label class="block mb-2 text-sm font-medium text-heading">Jumlah (Rp)</label>
                            <input type="text" wire:model.live="expense_amount"
                                class="block w-full px-3 py-2.5 bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base focus:ring-brand focus:border-brand shadow-xs"
                                placeholder="Contoh: 50000">
                        </div>

                        <div class="mb-4">
                            <label class="block mb-2 text-sm font-medium text-heading">Tanggal</label>
                            <input type="date" wire:model.live="expense_date"
                                class="block w-full px-3 py-2.5 bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base focus:ring-brand focus:border-brand shadow-xs">
                        </div>

                        <div class="flex flex-col gap-2 mt-6">
                            <button type="button" wire:click="saveExpense" @click="closeExpenseModal()"
                                class="w-full px-4 py-2 bg-red-500 hover:bg-red-600 text-white text-sm font-semibold rounded-md transition-colors">
                                Simpan Pengeluaran
                            </button>
                            <button type="button" @click="closeExpenseModal()"
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