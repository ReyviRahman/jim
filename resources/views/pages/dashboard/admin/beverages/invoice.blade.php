<?php

namespace App\Livewire\Pages\Dashboard\Admin\Beverages;

use App\Models\BeverageInvoice;
use App\Models\BeverageInvoiceItem;
use Livewire\Component;
use Livewire\Attributes\Layout;

new #[Layout('layouts::admin')] class extends Component
{
    public $deleteId = null;
    public $showDeleteModal = false;
    public $startDate = '';
    public $endDate = '';

    public function mount()
    {
        $this->startDate = now()->format('Y-m-d');
        $this->endDate = now()->format('Y-m-d');
    }

    public function getInvoicesProperty()
    {
        $query = BeverageInvoice::with('items');

        if ($this->startDate && $this->endDate) {
            $query->whereDate('tanggal_order', '>=', $this->startDate)
                  ->whereDate('tanggal_order', '<=', $this->endDate);
        } elseif ($this->startDate) {
            $query->whereDate('tanggal_order', '>=', $this->startDate);
        } elseif ($this->endDate) {
            $query->whereDate('tanggal_order', '<=', $this->endDate);
        }

        return $query->latest()->get();
    }

    public function getTotalSemuaProperty(): int
    {
        return $this->invoices->sum(function ($invoice) {
            return $invoice->items->sum('total');
        });
    }

    public function getTotalPpnSemuaProperty(): int
    {
        return $this->invoices->sum(function ($invoice) {
            return $invoice->items->sum('biaya_ppn');
        });
    }

    public function confirmDelete($id)
    {
        $this->deleteId = $id;
        $this->showDeleteModal = true;
    }

    public function cancelDelete()
    {
        $this->deleteId = null;
        $this->showDeleteModal = false;
    }

    public function deleteInvoice()
    {
        if (!$this->deleteId) return;

        $invoice = BeverageInvoice::find($this->deleteId);
        if ($invoice) {
            $invoice->items()->delete();
            $invoice->delete();
            session()->flash('success', 'Invoice berhasil dihapus.');
        }

        $this->cancelDelete();
    }

    public function exportExcel()
    {
        $filename = 'invoice-pembelian-minuman';
        if ($this->startDate || $this->endDate) {
            $filename .= '-' . ($this->startDate ?: 'all') . '_sampai_' . ($this->endDate ?: 'all');
        } else {
            $filename .= '-' . now()->format('Y-m-d');
        }
        $filename .= '.xlsx';

        return (new \App\Exports\BeverageInvoiceExport($this->startDate, $this->endDate))->download($filename);
    }

    public function with(): array
    {
        return [];
    }
};
?>

<div>
    <div class="flex sm:flex-row flex-col justify-between items-start sm:items-center mb-6 gap-4">
        <h5 class="text-xl font-semibold text-heading">Invoice Pembelian Minuman</h5>
        <div class="flex flex-col sm:flex-row items-start sm:items-center gap-2">
            <div class="flex items-center gap-2">
                <input type="date" wire:model.live="startDate" placeholder="Tanggal Mulai"
                    class="px-3 py-2 text-sm border border-default-medium rounded-md bg-white focus:outline-none focus:ring-2 focus:ring-brand focus:border-transparent">
                <span class="text-sm text-body">sampai</span>
                <input type="date" wire:model.live="endDate" placeholder="Tanggal Akhir"
                    class="px-3 py-2 text-sm border border-default-medium rounded-md bg-white focus:outline-none focus:ring-2 focus:ring-brand focus:border-transparent">
            </div>
            <div class="flex items-center gap-2">
                <button type="button" wire:click="exportExcel"
                    class="inline-flex items-center gap-1.5 px-4 py-2.5 text-sm font-medium text-white bg-emerald-600 hover:bg-emerald-700 rounded-md transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M15 18a3 3 0 1 0-6 0"/><path d="M15 12a3 3 0 1 0-6 0"/><path d="M10.2 20.4 9 23l-1.2-2.6"/><path d="M6 20H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h10.586a2 2 0 0 1 1.414.586l3.414 3.414A2 2 0 0 1 20 8.414V18a2 2 0 0 1-2 2h-2"/></svg>
                    Export Excel
                </button>
                <a href="{{ route('admin.beverages.invoice.create') }}" wire:navigate
                    class="inline-flex items-center gap-1.5 px-4 py-2.5 text-sm font-medium text-white bg-brand hover:bg-brand-strong rounded-md transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14"/><path d="M5 12h14"/></svg>
                    Buat Invoice
                </a>
            </div>
        </div>
    </div>

    @if (session()->has('success'))
        <div class="p-4 mb-4 text-sm text-green-800 rounded-lg bg-green-50" role="alert">
            <span class="font-medium">Sukses!</span> {{ session('success') }}
        </div>
    @endif

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
                        <th scope="col" class="px-4 py-3 font-medium text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->invoices as $invoice)
                        @php
                            $itemCount = $invoice->items->count();
                            $grandTotal = $invoice->items->sum('total');
                            $statusBadge = $invoice->status === 'lunas'
                                ? 'bg-emerald-100 text-emerald-700'
                                : 'bg-yellow-100 text-yellow-700';
                            $metodeMap = [
                                'cash' => 'Cash',
                                'tf_bca' => 'TF BCA',
                                'qris' => 'QRIS',
                                'hutang' => 'Hutang',
                            ];
                            $metodeBadge = match($invoice->metode_pembayaran) {
                                'cash' => 'bg-emerald-100 text-emerald-700',
                                'tf_bca', 'qris' => 'bg-blue-100 text-blue-700',
                                'hutang' => 'bg-purple-100 text-purple-700',
                                default => 'bg-gray-100 text-gray-700',
                            };
                            $metodeName = $metodeMap[$invoice->metode_pembayaran] ?? $invoice->metode_pembayaran;
                        @endphp

                        @if($itemCount > 0)
                            @foreach($invoice->items as $itemIndex => $item)
                                <tr class="border-b border-default hover:bg-neutral-secondary-medium">
                                    @if($itemIndex === 0)
                                        <td class="px-4 py-3 whitespace-nowrap" rowspan="{{ $itemCount }}">{{ $invoice->tanggal_order->format('d M Y') }}</td>
                                        <td class="px-4 py-3 whitespace-nowrap" rowspan="{{ $itemCount }}">{{ $invoice->tanggal_menerima?->format('d M Y') ?? '-' }}</td>
                                        <td class="px-4 py-3 whitespace-nowrap" rowspan="{{ $itemCount }}">{{ $invoice->diterima_oleh ?? '-' }}</td>
                                        <td class="px-4 py-3 whitespace-nowrap font-semibold" rowspan="{{ $itemCount }}">{{ $invoice->no_faktur }}</td>
                                    @endif

                                    <td class="px-4 py-3 whitespace-nowrap">{{ $item->nama_barang }}</td>
                                    <td class="px-4 py-3 text-center whitespace-nowrap">{{ $item->qty }}</td>
                                    <td class="px-4 py-3 text-right whitespace-nowrap">Rp {{ number_format($item->harga_perdus, 0, ',', '.') }}</td>
                                    <td class="px-4 py-3 text-right whitespace-nowrap">Rp {{ number_format($item->biaya_ppn, 0, ',', '.') }}</td>
                                    <td class="px-4 py-3 text-right whitespace-nowrap">Rp {{ number_format($item->total, 0, ',', '.') }}</td>

                                    @if($itemIndex === 0)
                                        <td class="px-4 py-3 text-right whitespace-nowrap font-semibold text-emerald-600" rowspan="{{ $itemCount }}">
                                            Rp {{ number_format($grandTotal, 0, ',', '.') }}
                                        </td>
                                        <td class="px-4 py-3 text-center whitespace-nowrap" rowspan="{{ $itemCount }}">
                                            <span class="px-2 py-1 rounded-full text-xs font-semibold {{ $statusBadge }}">
                                                {{ ucfirst($invoice->status) }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap" rowspan="{{ $itemCount }}">
                                            <span class="px-2 py-1 rounded-full text-xs font-semibold {{ $metodeBadge }}">
                                                {{ $metodeName }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-center whitespace-nowrap" rowspan="{{ $itemCount }}">
                                            @if(auth()->check() && auth()->user()->role === 'admin')
                                                <div class="flex items-center justify-center gap-1">
                                                    <a href="{{ route('admin.beverages.invoice.edit', $invoice->id) }}" wire:navigate
                                                        class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium text-blue-700 bg-blue-50 border border-blue-200 rounded-md hover:bg-blue-100">
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/><path d="m15 5 4 4"/></svg>
                                                        Edit
                                                    </a>
                                                    <button type="button" wire:click="confirmDelete({{ $invoice->id }})"
                                                        class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium text-red-700 bg-red-50 border border-red-200 rounded-md hover:bg-red-100">
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/></svg>
                                                        Hapus
                                                    </button>
                                                </div>
                                            @endif
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        @else
                            <tr class="border-b border-default hover:bg-neutral-secondary-medium">
                                <td class="px-4 py-3 whitespace-nowrap">{{ $invoice->tanggal_order->format('d M Y') }}</td>
                                <td class="px-4 py-3 whitespace-nowrap">{{ $invoice->tanggal_menerima?->format('d M Y') ?? '-' }}</td>
                                <td class="px-4 py-3 whitespace-nowrap">{{ $invoice->diterima_oleh ?? '-' }}</td>
                                <td class="px-4 py-3 whitespace-nowrap font-semibold">{{ $invoice->no_faktur }}</td>
                                <td class="px-4 py-3 text-center text-gray-500" colspan="5">Belum ada item</td>
                                <td class="px-4 py-3 text-right whitespace-nowrap font-semibold text-emerald-600">Rp 0</td>
                                <td class="px-4 py-3 text-center whitespace-nowrap">
                                    <span class="px-2 py-1 rounded-full text-xs font-semibold {{ $statusBadge }}">
                                        {{ ucfirst($invoice->status) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <span class="px-2 py-1 rounded-full text-xs font-semibold {{ $metodeBadge }}">
                                        {{ $metodeName }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center whitespace-nowrap">
                                    @if(auth()->check() && auth()->user()->role === 'admin')
                                        <div class="flex items-center justify-center gap-1">
                                            <a href="{{ route('admin.beverages.invoice.edit', $invoice->id) }}" wire:navigate
                                                class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium text-blue-700 bg-blue-50 border border-blue-200 rounded-md hover:bg-blue-100">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/><path d="m15 5 4 4"/></svg>
                                                Edit
                                            </a>
                                            <button type="button" wire:click="confirmDelete({{ $invoice->id }})"
                                                class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium text-red-700 bg-red-50 border border-red-200 rounded-md hover:bg-red-100">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/></svg>
                                                Hapus
                                            </button>
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr>
                            <td colspan="13" class="px-4 py-8 text-center text-gray-500">Belum ada invoice.</td>
                        </tr>
                    @endforelse
                </tbody>
                @if($this->invoices->isNotEmpty())
                <tfoot class="bg-neutral-secondary-medium border-t-2 border-default">
                    <tr>
                        <td colspan="9" class="px-4 py-3 text-right font-bold text-heading">GRAND TOTAL:</td>
                        <td class="px-4 py-3 text-right font-bold text-emerald-600">Rp {{ number_format($this->totalSemua, 0, ',', '.') }}</td>
                        <td colspan="3"></td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
    </div>

    @if(auth()->check() && auth()->user()->role === 'admin')
        @if ($showDeleteModal)
            <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" wire:click.self="cancelDelete">
                <div class="bg-white rounded-lg shadow-xl w-full max-w-md mx-4 p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h5 class="text-lg font-semibold text-heading">Konfirmasi Hapus</h5>
                        <button type="button" wire:click="cancelDelete" class="text-gray-400 hover:text-gray-600">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                        </button>
                    </div>
                    <p class="text-body mb-6">Apakah Anda yakin ingin menghapus invoice ini? Semua item terkait ikut terhapus.</p>
                    <div class="flex items-center justify-end gap-3">
                        <button type="button" wire:click="cancelDelete" class="px-4 py-2 text-sm font-medium text-body bg-neutral-secondary-medium border border-default-medium rounded-md hover:bg-neutral-secondary-strong transition-colors">
                            Batal
                        </button>
                        <button type="button" wire:click="deleteInvoice" class="px-4 py-2.5 text-white bg-red-600 hover:bg-red-700 rounded-md font-medium text-sm focus:outline-none">
                            Hapus
                        </button>
                    </div>
                </div>
            </div>
        @endif
    @endif
</div>
