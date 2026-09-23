<?php

namespace App\Livewire\Pages\Dashboard\Admin\Beverages;

use App\Models\BeverageInvoice;
use App\Actions\CancelBeverageInvoice;
use App\Actions\BeverageStockImpact;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Attributes\Layout;

new #[Layout('layouts::admin')] class extends Component
{
    #[Locked]
    public $deleteId = null;

    #[Locked]
    public array $deletePreview = [];

    public int $impactPage = 1;
    public string $deleteNotice = '';
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
        $this->deletePreview = app(CancelBeverageInvoice::class)->preview((int) $id);
        $this->impactPage = 1;
        $this->deleteNotice = '';
        $this->deleteId = $id;
        $this->showDeleteModal = true;
    }

    public function cancelDelete()
    {
        $this->deleteId = null;
        $this->showDeleteModal = false;
        $this->deletePreview = [];
        $this->deleteNotice = '';
    }

    public function deleteInvoice(): void
    {
        if (! $this->deleteId) {
            return;
        }
        $updated = app(CancelBeverageInvoice::class)->execute((int) $this->deleteId, $this->deletePreview['fingerprint'] ?? '');
        if ($updated !== null) {
            $this->deletePreview = $updated;
            $this->impactPage = 1;
            $this->deleteNotice = 'Data berubah atau belum dapat dikoreksi. Periksa kembali dampaknya sebelum konfirmasi.';
            return;
        }
        $message = ($this->deletePreview['legacy'] ?? true)
            ? 'Invoice arsip dihapus tanpa perubahan stok.'
            : 'Invoice dihapus. Stok '.count($this->deletePreview['products']).' produk dikoreksi dari '.$this->deletePreview['date'].' sampai '.$this->deletePreview['through'].'.';
        session()->flash('success', $message);
        $this->cancelDelete();
    }

    public function changeImpactPage(int $direction): void
    {
        $this->impactPage = max(1, $this->impactPage + ($direction > 0 ? 1 : -1));
    }

    public function getSnapshotChangesProperty()
    {
        if (! $this->deleteId || ($this->deletePreview['legacy'] ?? true)) {
            return null;
        }
        abort_unless(auth()->user()?->role === 'admin', 403);

        return app(BeverageStockImpact::class)
            ->snapshots(array_column($this->deletePreview['products'], 'id'), $this->deletePreview['date'])
            ->orderBy('tanggal')->orderBy('beverage_id')->orderBy('tipe')
            ->paginate(10, ['*'], 'impactPage', $this->impactPage);
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
        <div class="overflow-hidden">
            <table data-responsive-table data-responsive-breakpoint="xl" class="table-fixed w-full text-sm text-left rtl:text-right text-body">
                <thead class="text-sm text-body bg-neutral-secondary-medium border-b border-default-medium">
                    <tr>
                        <th scope="col" class="px-4 py-3 font-medium">Tanggal Order</th>
                        <th scope="col" class="px-4 py-3 font-medium">Tanggal Menerima</th>
                        <th scope="col" class="px-4 py-3 font-medium">Diterima Oleh</th>
                        <th scope="col" class="px-4 py-3 font-medium">No Faktur</th>
                        <th scope="col" class="px-4 py-3 font-medium">Nama Barang</th>
                        <th scope="col" class="px-4 py-3 font-medium text-center">Qty Dus</th>
                        <th scope="col" class="px-4 py-3 font-medium text-center">Total Pcs</th>
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
                                        <td class="px-4 py-3" rowspan="{{ $itemCount }}">{{ $invoice->tanggal_order->format('d M Y') }}</td>
                                        <td class="px-4 py-3" rowspan="{{ $itemCount }}">{{ $invoice->tanggal_menerima?->format('d M Y') ?? '-' }}</td>
                                        <td class="px-4 py-3" rowspan="{{ $itemCount }}">{{ $invoice->diterima_oleh ?? '-' }}</td>
                                        <td class="px-4 py-3 font-semibold" rowspan="{{ $itemCount }}">{{ $invoice->no_faktur }}
                                            @if (! $invoice->stock_posted_at) <p class="text-xs">Arsip — tidak terhubung stok</p> @endif
                                            @if ($invoice->image_path)
                                                <a href="{{ route('admin.beverages.invoice.image', $invoice) }}" target="_blank" rel="noopener noreferrer" class="text-blue-700 underline">Lihat foto</a>
                                            @endif
                                        </td>
                                    @endif

                                    <td class="px-4 py-3">{{ $item->nama_barang }}</td>
                                    <td class="px-4 py-3 text-center">{{ $item->qty }}</td>
                                    <td class="px-4 py-3 text-center">{{ $item->total_pcs ?? '—' }}</td>
                                    <td class="px-4 py-3 text-right">Rp {{ number_format($item->harga_perdus, 0, ',', '.') }}</td>
                                    <td class="px-4 py-3 text-right">Rp {{ number_format($item->biaya_ppn, 0, ',', '.') }}</td>
                                    <td class="px-4 py-3 text-right">Rp {{ number_format($item->total, 0, ',', '.') }}</td>

                                    @if($itemIndex === 0)
                                        <td class="px-4 py-3 text-right font-semibold text-emerald-600" rowspan="{{ $itemCount }}">
                                            Rp {{ number_format($grandTotal, 0, ',', '.') }}
                                        </td>
                                        <td class="px-4 py-3 text-center" rowspan="{{ $itemCount }}">
                                            <span class="px-2 py-1 rounded-full text-xs font-semibold {{ $statusBadge }}">
                                                {{ ucfirst($invoice->status) }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3" rowspan="{{ $itemCount }}">
                                            <span class="px-2 py-1 rounded-full text-xs font-semibold {{ $metodeBadge }}">
                                                {{ $metodeName }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-center" rowspan="{{ $itemCount }}">
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
                                <td class="px-4 py-3">{{ $invoice->tanggal_order->format('d M Y') }}</td>
                                <td class="px-4 py-3">{{ $invoice->tanggal_menerima?->format('d M Y') ?? '-' }}</td>
                                <td class="px-4 py-3">{{ $invoice->diterima_oleh ?? '-' }}</td>
                                <td class="px-4 py-3 font-semibold">{{ $invoice->no_faktur }}
                                            @if (! $invoice->stock_posted_at) <p class="text-xs">Arsip — tidak terhubung stok</p> @endif
                                            @if ($invoice->image_path)
                                                <a href="{{ route('admin.beverages.invoice.image', $invoice) }}" target="_blank" rel="noopener noreferrer" class="text-blue-700 underline">Lihat foto</a>
                                            @endif
                                        </td>
                                <td class="px-4 py-3 text-center text-gray-500" colspan="6">Belum ada item</td>
                                <td class="px-4 py-3 text-right font-semibold text-emerald-600">Rp 0</td>
                                <td class="px-4 py-3 text-center">
                                    <span class="px-2 py-1 rounded-full text-xs font-semibold {{ $statusBadge }}">
                                        {{ ucfirst($invoice->status) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="px-2 py-1 rounded-full text-xs font-semibold {{ $metodeBadge }}">
                                        {{ $metodeName }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center">
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
                            <td colspan="14" class="px-4 py-8 text-center text-gray-500">Belum ada invoice.</td>
                        </tr>
                    @endforelse
                </tbody>
                @if($this->invoices->isNotEmpty())
                <tfoot class="bg-neutral-secondary-medium border-t-2 border-default">
                    <tr>
                        <td colspan="10" class="px-4 py-3 text-right font-bold text-heading">GRAND TOTAL:</td>
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
                <div class="bg-white rounded-lg shadow-xl w-full max-w-3xl mx-4 p-6 max-h-[90vh] overflow-y-auto">
                    <div class="flex items-center justify-between mb-4">
                        <h5 class="text-lg font-semibold text-heading">Konfirmasi Hapus</h5>
                        <button type="button" wire:click="cancelDelete" class="text-gray-400 hover:text-gray-600">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                        </button>
                    </div>
                    <p class="text-body mb-4">Invoice {{ $deletePreview['invoice'] ?? '' }} akan dihapus beserta itemnya.</p>
                    @if ($deleteNotice) <p role="alert" class="mb-4 text-red-600">{{ $deleteNotice }}</p> @endif
                    @if ($deletePreview['legacy'] ?? true)
                        <p class="mb-4">Arsip — tidak terhubung stok. Tidak ada perubahan stok.</p>
                    @else
                        <p class="mb-4">Tanggal menerima {{ $deletePreview['date'] }}. Stok akhir hari itu dan stok awal/akhir setelahnya sampai {{ $deletePreview['through'] }} dikoreksi otomatis. Stok sebelumnya tidak berubah.</p>
                        @foreach ($deletePreview['products'] as $product)
                            <p wire:key="impact-product-{{ $product['id'] }}" class="mb-2">{{ $product['name'] }}: kurangi {{ $product['pcs'] }} pcs. Saldo sekarang {{ $product['before'] }} → {{ $product['after'] }}.</p>
                        @endforeach
                        @if ($deletePreview['error_count'])
                            <div role="alert" class="mb-4 text-red-600">
                                <p>Pembatalan ditahan: {{ $deletePreview['error_count'] }} masalah ditemukan. Periksa data stok terlebih dahulu.</p>
                                @foreach ($deletePreview['errors'] as $error) <p>{{ $error }}</p> @endforeach
                                @if ($deletePreview['error_count'] > 20) <p>Menampilkan 20 masalah pertama.</p> @endif
                            </div>
                        @endif
                        @php $changes = $this->snapshotChanges; $productsById = collect($deletePreview['products'])->keyBy('id'); @endphp
                        @if ($changes)
                            <p class="mt-4">Rincian snapshot, halaman {{ $changes->currentPage() }} dari {{ $changes->lastPage() }}</p>
                            @foreach ($changes as $change)
                                <p wire:key="snapshot-{{ $change->id }}" class="text-sm">{{ $change->tanggal->format('d/m/Y') }} · {{ $productsById[$change->beverage_id]['name'] }} · {{ $change->tipe === 'init' ? 'Awal' : 'Akhir' }}: {{ $change->jumlah }} → {{ $change->jumlah - $productsById[$change->beverage_id]['pcs'] }}</p>
                            @endforeach
                            <div class="flex gap-4 my-4">
                                <button type="button" wire:click="changeImpactPage(-1)" @disabled($changes->onFirstPage())>Sebelumnya</button>
                                <button type="button" wire:click="changeImpactPage(1)" @disabled(! $changes->hasMorePages())>Berikutnya</button>
                            </div>
                        @endif
                    @endif
                    <div class="flex items-center justify-end gap-3">
                        <button type="button" wire:click="cancelDelete" class="px-4 py-2 text-sm font-medium text-body bg-neutral-secondary-medium border border-default-medium rounded-md hover:bg-neutral-secondary-strong transition-colors">
                            Batal
                        </button>
                        <button type="button" wire:click="deleteInvoice" wire:loading.attr="disabled" @disabled(($deletePreview['error_count'] ?? 0) > 0) class="px-4 py-2.5 text-white bg-red-600 hover:bg-red-700 rounded-md font-medium text-sm focus:outline-none">
                            Hapus
                        </button>
                    </div>
                </div>
            </div>
        @endif
    @endif
</div>
