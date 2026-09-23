@props(['preview', 'changes', 'notice'])

<div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" wire:click.self="closeDeleteModal" role="dialog" aria-modal="true" aria-labelledby="sale-delete-title">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-3xl max-h-[90vh] overflow-y-auto mx-4 p-6">
        <h5 id="sale-delete-title" class="text-lg font-semibold mb-4">Pratinjau Penghapusan Transaksi #{{ $preview['sale_id'] }}</h5>
        <p>{{ $preview['name'] }} · {{ $preview['date'] }} · {{ $preview['method'] }}</p>
        @if ($notice)
            <p role="alert" class="text-amber-800 my-3">{{ $notice }}</p>
        @endif
        @if ($preview['stock_affecting'])
            <p class="my-3">Pengembalian {{ $preview['pcs'] }} pcs. Koreksi stok {{ $preview['date'] }} sampai {{ $preview['through'] }}.</p>
            <p>Stok sebelum tanggal transaksi dan stok awal pada tanggal transaksi tidak berubah. Stok akhir tanggal transaksi dan stok berikutnya dikoreksi otomatis.</p>
            @foreach ($preview['products'] as $product)
                <p class="my-3 font-semibold">{{ $product['name'] }}: saldo sekarang {{ $product['before'] }} → {{ $product['after'] }} pcs.</p>
            @endforeach
        @else
            <p class="my-3">Transaksi ini tidak memengaruhi stok. Saldo dan snapshot tidak diubah.</p>
        @endif
        @if ($preview['settlement_count'])
            <p>{{ $preview['settlement_count'] }} catatan pelunasan ikut dihapus tanpa pengembalian stok tambahan.</p>
        @endif
        @if ($preview['parent'])
            <p>Status hutang #{{ $preview['parent']['id'] }} setelah penghapusan: {{ $preview['parent']['is_lunas_after'] ? 'Lunas, masih ada catatan pelunasan.' : 'Belum lunas.' }}</p>
        @endif
        @foreach ($preview['deposit_returns'] as $deposit)
            <p class="my-3">Deposit {{ $deposit['customer'] }} dikembalikan Rp {{ number_format($deposit['amount'], 0, ',', '.') }}. Saldo Rp {{ number_format($deposit['before'], 0, ',', '.') }} → Rp {{ number_format($deposit['after'], 0, ',', '.') }}.</p>
        @endforeach
        @foreach ($preview['deposit_removals'] as $deposit)
            <p class="my-3">Deposit kembalian {{ $deposit['customer'] }} sebesar Rp {{ number_format($deposit['amount'], 0, ',', '.') }} ikut dihapus.</p>
        @endforeach
        @if ($preview['error_count'])
            <div role="alert" class="my-3 text-red-600">
                <p>Penghapusan ditahan: {{ $preview['error_count'] }} masalah ditemukan.</p>
                @foreach ($preview['errors'] as $error)<p>{{ $error }}</p>@endforeach
            </div>
        @endif
        @if ($changes && $changes->count())
            <table class="w-full my-4 text-sm">
                <thead><tr><th>Tanggal</th><th>Snapshot</th><th>Sebelum</th><th>Sesudah</th></tr></thead>
                <tbody>
                    @foreach ($changes as $snapshot)
                        <tr wire:key="sale-snapshot-{{ $snapshot->id }}" class="text-center">
                            <td>{{ $snapshot->tanggal->format('d/m/Y') }}</td><td>{{ $snapshot->tipe === 'init' ? 'Awal' : 'Akhir' }}</td>
                            <td>{{ $snapshot->jumlah }}</td><td>{{ $snapshot->jumlah + $preview['pcs'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="flex justify-between my-3">
                <button type="button" wire:click="changeSaleImpactPage(-1)" @disabled($changes->onFirstPage())>Sebelumnya</button>
                <span>Halaman {{ $changes->currentPage() }} / {{ $changes->lastPage() }}</span>
                <button type="button" wire:click="changeSaleImpactPage(1)" @disabled(! $changes->hasMorePages())>Berikutnya</button>
            </div>
        @endif
        <div class="flex justify-end gap-3 mt-4">
            <button type="button" wire:click="closeDeleteModal" class="px-4 py-2 border rounded-md">Batal</button>
            <button type="button" wire:click="deleteSale" wire:loading.attr="disabled" @disabled($preview['error_count']) class="px-4 py-2 bg-red-600 text-white rounded-md disabled:opacity-50">Hapus Transaksi</button>
        </div>
    </div>
</div>
