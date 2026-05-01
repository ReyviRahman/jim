<?php

namespace App\Livewire\Pages\Dashboard\Admin\Beverages;

use App\Models\BeverageInvoice;
use App\Models\BeverageInvoiceItem;
use Livewire\Component;
use Livewire\Attributes\Layout;

new #[Layout('layouts::admin')] class extends Component
{
    public $invoiceId;
    public $no_faktur = '';
    public $tanggal_order = '';
    public $tanggal_menerima = '';
    public $diterima_oleh = '';
    public $status = 'pending';
    public $metode_pembayaran = 'cash';
    public $items = [];

    public function mount($invoice)
    {
        $this->invoiceId = $invoice;
        $inv = BeverageInvoice::with('items')->findOrFail($invoice);

        $this->no_faktur = $inv->no_faktur;
        $this->tanggal_order = $inv->tanggal_order->format('Y-m-d');
        $this->tanggal_menerima = $inv->tanggal_menerima?->format('Y-m-d') ?? '';
        $this->diterima_oleh = $inv->diterima_oleh ?? '';
        $this->status = $inv->status;
        $this->metode_pembayaran = $inv->metode_pembayaran;

        foreach ($inv->items as $item) {
            $this->items[] = [
                'id' => $item->id,
                'nama_barang' => $item->nama_barang,
                'qty' => $item->qty,
                'harga_perdus' => $item->harga_perdus,
                'biaya_ppn' => $item->biaya_ppn,
                'total' => $item->total,
            ];
        }
    }

    public function addItem()
    {
        $this->items[] = [
            'id' => null,
            'nama_barang' => '',
            'qty' => '',
            'harga_perdus' => '',
            'biaya_ppn' => '',
            'total' => 0,
        ];
    }

    public function removeItem($index)
    {
        if (count($this->items) > 1) {
            unset($this->items[$index]);
            $this->items = array_values($this->items);
        }
    }

    public function recalculateTotal($index)
    {
        $qty = intval($this->items[$index]['qty'] ?? 0);
        $harga = intval($this->items[$index]['harga_perdus'] ?? 0);
        $ppn = intval($this->items[$index]['biaya_ppn'] ?? 0);
        $this->items[$index]['total'] = ($qty * $harga) + $ppn;
    }

    public function getGrandTotalProperty(): int
    {
        return collect($this->items)->sum('total');
    }

    public function update()
    {
        $this->validate([
            'no_faktur' => 'required|unique:beverage_invoices,no_faktur,' . $this->invoiceId,
            'tanggal_order' => 'required|date',
            'status' => 'required|in:pending,lunas',
            'metode_pembayaran' => 'required|in:cash,tf_bca,qris,hutang',
            'items' => 'required|array|min:1',
            'items.*.nama_barang' => 'required',
            'items.*.qty' => 'required|integer|min:1',
            'items.*.harga_perdus' => 'required|integer|min:0',
        ]);

        $invoice = BeverageInvoice::findOrFail($this->invoiceId);

        $invoice->update([
            'no_faktur' => $this->no_faktur,
            'tanggal_order' => $this->tanggal_order,
            'tanggal_menerima' => $this->tanggal_menerima ?: null,
            'diterima_oleh' => $this->diterima_oleh ?: null,
            'status' => $this->status,
            'metode_pembayaran' => $this->metode_pembayaran,
        ]);

        $existingItemIds = collect($this->items)->where('id', '!=', null)->pluck('id')->toArray();
        $invoice->items()->whereNotIn('id', $existingItemIds)->delete();

        foreach ($this->items as $item) {
            if (!empty($item['nama_barang'])) {
                if ($item['id']) {
                    $invoiceItem = BeverageInvoiceItem::find($item['id']);
                    if ($invoiceItem) {
                        $invoiceItem->update([
                            'nama_barang' => $item['nama_barang'],
                            'qty' => intval($item['qty']),
                            'harga_perdus' => intval($item['harga_perdus']),
                            'biaya_ppn' => intval($item['biaya_ppn'] ?? 0),
                            'total' => intval($item['total']),
                        ]);
                    }
                } else {
                    BeverageInvoiceItem::create([
                        'beverage_invoice_id' => $invoice->id,
                        'nama_barang' => $item['nama_barang'],
                        'qty' => intval($item['qty']),
                        'harga_perdus' => intval($item['harga_perdus']),
                        'biaya_ppn' => intval($item['biaya_ppn'] ?? 0),
                        'total' => intval($item['total']),
                    ]);
                }
            }
        }

        session()->flash('success', 'Invoice berhasil diperbarui.');

        return $this->redirectRoute('admin.beverages.invoice', navigate: true);
    }

    public function with(): array
    {
        return [];
    }
};
?>

<div>
    <div class="flex sm:flex-row flex-col justify-between items-center mb-6">
        <h5 class="text-xl font-semibold text-heading">Edit Invoice Pembelian</h5>
    </div>

    @if (session()->has('success'))
        <div class="p-4 mb-4 text-sm text-green-800 rounded-lg bg-green-50" role="alert">
            <span class="font-medium">Sukses!</span> {{ session('success') }}
        </div>
    @endif

    <form wire:submit.prevent="update" class="bg-neutral-primary-soft shadow-xs rounded-md border border-default p-6">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
            <div>
                <label for="no_faktur" class="block mb-2.5 text-sm font-medium text-heading">No Faktur *</label>
                <input type="text" id="no_faktur" wire:model="no_faktur"
                    class="block w-full px-3 py-2.5 bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base focus:ring-brand focus:border-brand shadow-xs placeholder:text-body"
                    placeholder="Contoh: INV-20250101-001">
                @error('no_faktur') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
            </div>

            <div>
                <label for="tanggal_order" class="block mb-2.5 text-sm font-medium text-heading">Tanggal Order *</label>
                <input type="date" id="tanggal_order" wire:model="tanggal_order"
                    class="block w-full px-3 py-2.5 bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base focus:ring-brand focus:border-brand shadow-xs">
                @error('tanggal_order') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
            </div>

            <div>
                <label for="tanggal_menerima" class="block mb-2.5 text-sm font-medium text-heading">Tanggal Menerima</label>
                <input type="date" id="tanggal_menerima" wire:model="tanggal_menerima"
                    class="block w-full px-3 py-2.5 bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base focus:ring-brand focus:border-brand shadow-xs">
            </div>

            <div>
                <label for="diterima_oleh" class="block mb-2.5 text-sm font-medium text-heading">Diterima Oleh</label>
                <input type="text" id="diterima_oleh" wire:model="diterima_oleh"
                    class="block w-full px-3 py-2.5 bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base focus:ring-brand focus:border-brand shadow-xs placeholder:text-body"
                    placeholder="Nama penerima">
            </div>

            <div>
                <label for="status" class="block mb-2.5 text-sm font-medium text-heading">Status</label>
                <select id="status" wire:model="status"
                    class="block w-full px-3 py-2.5 bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base focus:ring-brand focus:border-brand shadow-xs">
                    <option value="pending">Pending</option>
                    <option value="lunas">Lunas</option>
                </select>
            </div>

            <div>
                <label for="metode_pembayaran" class="block mb-2.5 text-sm font-medium text-heading">Metode Pembayaran</label>
                <select id="metode_pembayaran" wire:model="metode_pembayaran"
                    class="block w-full px-3 py-2.5 bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base focus:ring-brand focus:border-brand shadow-xs">
                    <option value="cash">Cash</option>
                    <option value="tf_bca">Transfer BCA</option>
                    <option value="qris">QRIS</option>
                    <option value="hutang">Hutang</option>
                </select>
            </div>
        </div>

        <div class="mb-4">
            <div class="flex items-center justify-between mb-3">
                <h6 class="text-lg font-semibold text-heading">Daftar Barang</h6>
                <button type="button" wire:click="addItem"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-white bg-emerald-600 hover:bg-emerald-700 rounded-md transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14"/><path d="M5 12h14"/></svg>
                    Tambah Barang
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left rtl:text-right text-body">
                    <thead class="text-sm text-body bg-neutral-secondary-medium border-b border-default-medium">
                        <tr>
                            <th scope="col" class="px-4 py-3 font-medium w-48">Nama Barang</th>
                            <th scope="col" class="px-4 py-3 font-medium text-center w-24">Qty</th>
                            <th scope="col" class="px-4 py-3 font-medium text-right w-32">Harga Perdus</th>
                            <th scope="col" class="px-4 py-3 font-medium text-right w-32">Biaya PPN</th>
                            <th scope="col" class="px-4 py-3 font-medium text-right w-36">Total</th>
                            <th scope="col" class="px-4 py-3 font-medium text-center w-16">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($items as $index => $item)
                            <tr class="border-b border-default hover:bg-neutral-secondary-medium">
                                <td class="px-4 py-2">
                                    <input type="text"
                                        wire:model.live="items.{{ $index }}.nama_barang"
                                        class="block w-full px-3 py-2 bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base focus:ring-brand focus:border-brand shadow-xs placeholder:text-body"
                                        placeholder="Nama barang">
                                </td>
                                <td class="px-4 py-2">
                                    <input type="text" inputmode="numeric" pattern="[0-9]*"
                                        wire:model.live="items.{{ $index }}.qty"
                                        wire:keyup="recalculateTotal({{ $index }})"
                                        class="block w-full px-2 py-2 bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base focus:ring-brand focus:border-brand shadow-xs text-center"
                                        placeholder="0">
                                </td>
                                <td class="px-4 py-2">
                                    <input type="text" inputmode="numeric" pattern="[0-9]*"
                                        wire:model.live="items.{{ $index }}.harga_perdus"
                                        wire:keyup="recalculateTotal({{ $index }})"
                                        class="block w-full px-2 py-2 bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base focus:ring-brand focus:border-brand shadow-xs text-right"
                                        placeholder="Rp 0">
                                </td>
                                <td class="px-4 py-2">
                                    <input type="text" inputmode="numeric" pattern="[0-9]*"
                                        wire:model.live="items.{{ $index }}.biaya_ppn"
                                        wire:keyup="recalculateTotal({{ $index }})"
                                        class="block w-full px-2 py-2 bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base focus:ring-brand focus:border-brand shadow-xs text-right"
                                        placeholder="Rp 0">
                                </td>
                                <td class="px-4 py-2 text-right font-semibold text-heading">
                                    Rp {{ number_format($item['total'], 0, ',', '.') }}
                                </td>
                                <td class="px-4 py-2 text-center">
                                    @if (count($items) > 1)
                                        <button type="button" wire:click="removeItem({{ $index }})" class="text-red-500 hover:text-red-700">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/></svg>
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-neutral-secondary-medium border-t-2 border-default">
                        <tr>
                            <td colspan="4" class="px-4 py-3 text-right font-bold text-heading">GRAND TOTAL:</td>
                            <td class="px-4 py-3 text-right font-bold text-emerald-600">Rp {{ number_format($this->grandTotal, 0, ',', '.') }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <a href="{{ route('admin.beverages.invoice') }}" wire:navigate class="inline-flex items-center gap-1.5 px-4 py-2.5 text-sm font-medium text-body bg-neutral-secondary-medium border border-default-medium rounded-md hover:bg-neutral-secondary-strong transition-colors">
                Batal
            </a>
            <button type="submit" class="px-6 py-2.5 text-white bg-brand hover:bg-brand-strong rounded-md font-medium text-sm focus:outline-none">
                Simpan Perubahan
            </button>
        </div>
    </form>
</div>
