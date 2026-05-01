<?php

namespace App\Livewire\Pages\Dashboard\Admin\Beverages;

use App\Models\BeverageInvoice;
use Livewire\Component;
use Livewire\Attributes\Layout;

new #[Layout('layouts::admin')] class extends Component
{
    public function getInvoicesProperty()
    {
        return BeverageInvoice::with('items')->latest()->get();
    }

    public function getGrandTotalAllAttribute(): int
    {
        return $this->invoices->sum(function ($invoice) {
            return $invoice->items->sum('total');
        });
    }

    public function getTotalPpnAllAttribute(): int
    {
        return $this->invoices->sum(function ($invoice) {
            return $invoice->items->sum('biaya_ppn');
        });
    }

    public function with(): array
    {
        return [];
    }
};
?>

<div>
    <div class="flex sm:flex-row flex-col justify-between items-center mb-6">
        <h5 class="text-xl font-semibold text-heading">Invoice Pembelian Minuman</h5>
        <a href="{{ route('admin.beverages.invoice.create') }}" wire:navigate
            class="inline-flex items-center gap-1.5 px-4 py-2.5 text-sm font-medium text-white bg-brand hover:bg-brand-strong rounded-md transition-colors">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14"/><path d="M5 12h14"/></svg>
            Buat Invoice
        </a>
    </div>

    <div class="bg-neutral-primary-soft shadow-xs rounded-md border border-default">
        <div class="p-4 border-b border-default-medium">
            <h6 class="text-lg font-semibold text-heading">Daftar Invoice</h6>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left rtl:text-right text-body">
                <thead class="text-sm text-body bg-neutral-secondary-medium border-b border-default-medium">
                    <tr>
                        <th scope="col" class="px-4 py-3 font-medium">Tanggal Order</th>
                        <th scope="col" class="px-4 py-3 font-medium">Tanggal Menerima</th>
                        <th scope="col" class="px-4 py-3 font-medium">Diterima Oleh</th>
                        <th scope="col" class="px-4 py-3 font-medium">No Faktur</th>
                        <th scope="col" class="px-4 py-3 font-medium">Nama Barang</th>
                        <th scope="col" class="px-4 py-3 font-medium text-center">Qty</th>
                        <th scope="col" class="px-4 py-3 font-medium text-right">Harga Perdus</th>
                        <th scope="col" class="px-4 py-3 font-medium text-right">Biaya PPN</th>
                        <th scope="col" class="px-4 py-3 font-medium text-right">Total</th>
                        <th scope="col" class="px-4 py-3 font-medium text-right">Total Bayar</th>
                        <th scope="col" class="px-4 py-3 font-medium text-center">Status</th>
                        <th scope="col" class="px-4 py-3 font-medium">Metode Pembayaran</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->invoices as $invoice)
                        @php
                            $itemCount = $invoice->items->count();
                            $firstItem = $invoice->items->first();
                            $grandTotal = $invoice->items->sum('total');
                            $totalPpn = $invoice->items->sum('biaya_ppn');
                            $totalQty = $invoice->items->sum('qty');
                        @endphp
                        <tr class="border-b border-default hover:bg-neutral-secondary-medium">
                            <td class="px-4 py-3 whitespace-nowrap">{{ $invoice->tanggal_order->format('d M Y') }}</td>
                            <td class="px-4 py-3 whitespace-nowrap">{{ $invoice->tanggal_menerima?->format('d M Y') ?? '-' }}</td>
                            <td class="px-4 py-3 whitespace-nowrap">{{ $invoice->diterima_oleh ?? '-' }}</td>
                            <td class="px-4 py-3 whitespace-nowrap font-semibold">{{ $invoice->no_faktur }}</td>
                            <td class="px-4 py-3 whitespace-nowrap">{{ $firstItem->nama_barang ?? '-' }}{{ $itemCount > 1 ? '...' : '' }}</td>
                            <td class="px-4 py-3 text-center whitespace-nowrap">{{ $totalQty }}</td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">Rp {{ number_format($firstItem->harga_perdus ?? 0, 0, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">Rp {{ number_format($totalPpn, 0, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right whitespace-nowrap font-semibold">Rp {{ number_format($grandTotal, 0, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right whitespace-nowrap font-semibold text-emerald-600">Rp {{ number_format($grandTotal, 0, ',', '.') }}</td>
                            <td class="px-4 py-3 text-center whitespace-nowrap">
                                @php
                                    $statusBadge = $invoice->status === 'lunas'
                                        ? 'bg-emerald-100 text-emerald-700'
                                        : 'bg-yellow-100 text-yellow-700';
                                @endphp
                                <span class="px-2 py-1 rounded-full text-xs font-semibold {{ $statusBadge }}">
                                    {{ ucfirst($invoice->status) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                @php
                                    $metodeMap = [
                                        'cash' => 'Cash',
                                        'tf_bca' => 'TF BCA',
                                        'qris' => 'QRIS',
                                        'hutang' => 'Hutang',
                                    ];
                                    $metodeBadge = match($invoice->metode_pembayaran) {
                                        'cash' => 'bg-emerald-100 text-emerald-700',
                                        'tf_bca' => 'bg-blue-100 text-blue-700',
                                        'qris' => 'bg-blue-100 text-blue-700',
                                        'hutang' => 'bg-purple-100 text-purple-700',
                                        default => 'bg-gray-100 text-gray-700',
                                    };
                                @endphp
                                <span class="px-2 py-1 rounded-full text-xs font-semibold {{ $metodeBadge }}">
                                    {{ $metodeMap[$invoice->metode_pembayaran] ?? $invoice->metode_pembayaran }}
                                </span>
                            </td>
                        </tr>
                        @if($itemCount > 1)
                            @foreach($invoice->items as $item)
                                @if(!$loop->first)
                                <tr class="border-b border-default hover:bg-neutral-secondary-medium bg-neutral-secondary-soft">
                                    <td class="px-4 py-3 whitespace-nowrap" colspan="4"></td>
                                    <td class="px-4 py-3 whitespace-nowrap">{{ $item->nama_barang }}</td>
                                    <td class="px-4 py-3 text-center whitespace-nowrap">{{ $item->qty }}</td>
                                    <td class="px-4 py-3 text-right whitespace-nowrap">Rp {{ number_format($item->harga_perdus, 0, ',', '.') }}</td>
                                    <td class="px-4 py-3 text-right whitespace-nowrap">Rp {{ number_format($item->biaya_ppn, 0, ',', '.') }}</td>
                                    <td class="px-4 py-3 text-right whitespace-nowrap">Rp {{ number_format($item->total, 0, ',', '.') }}</td>
                                    <td class="px-4 py-3 text-right whitespace-nowrap" colspan="4"></td>
                                </tr>
                                @endif
                            @endforeach
                        @endif
                    @empty
                        <tr>
                            <td colspan="12" class="px-4 py-8 text-center text-gray-500">Belum ada invoice.</td>
                        </tr>
                    @endforelse
                </tbody>
                @if($this->invoices->isNotEmpty())
                <tfoot class="bg-neutral-secondary-medium border-t-2 border-default">
                    <tr>
                        <td colspan="8" class="px-4 py-3 text-right font-bold text-heading">GRAND TOTAL:</td>
                        <td class="px-4 py-3 text-right font-bold text-heading">Rp {{ number_format($this->grandTotalAll, 0, ',', '.') }}</td>
                        <td class="px-4 py-3 text-right font-bold text-emerald-600">Rp {{ number_format($this->grandTotalAll, 0, ',', '.') }}</td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
    </div>
</div>
