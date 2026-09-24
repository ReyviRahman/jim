<table>
    <thead>
        <tr>
            <th>Tanggal Order</th>
            <th>Tanggal Menerima</th>
            <th>Diterima Oleh</th>
            <th>No Faktur</th>
            <th>Nama Barang</th>
            <th>Qty Dus</th>
            <th>Total Pcs</th>
            <th>Harga Perdus</th>
            <th>Biaya PPN</th>
            <th>Total</th>
            <th>Total Bayar</th>
            <th>Status</th>
            <th>Metode Pembayaran</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($monthlyData as $month)
            @if (!$loop->first)
            <tr>
                <td colspan="13"></td>
            </tr>
            @endif
            <tr>
                <td colspan="13" style="background-color: #e5e7eb; font-weight: bold;">Bulan {{ $month['name'] }}</td>
            </tr>
            @foreach ($month['invoices'] as $invoice)
                @php
                    $itemCount = $invoice->items->count();
                    $grandTotal = $invoice->items->sum('total');
                    $metodeMap = [
                        'cash' => 'Cash',
                        'tf_bca' => 'TF BCA',
                        'qris' => 'QRIS',
                        'hutang' => 'Hutang',
                    ];
                    $metodeName = $metodeMap[$invoice->metode_pembayaran] ?? $invoice->metode_pembayaran;
                @endphp

                @if($itemCount > 0)
                    @foreach($invoice->items as $itemIndex => $item)
                        <tr>
                            <td>{{ $invoice->tanggal_order->format('d M Y') }}</td>
                            <td>{{ $invoice->tanggal_menerima?->format('d M Y') ?? '-' }}</td>
                            <td>{{ $invoice->diterima_oleh ?? '-' }}</td>
                            <td>{{ $invoice->no_faktur }}</td>
                            <td>{{ $item->nama_barang }}</td>
                            <td>{{ $item->qty }}</td>
                            <td>{{ $item->total_pcs ?? '—' }}</td>
                            <td>{{ $item->harga_perdus }}</td>
                            <td>{{ $item->biaya_ppn }}</td>
                            <td>{{ $item->total }}</td>
                            @if($itemIndex === 0)
                                <td>{{ $grandTotal }}</td>
                                <td>{{ ucfirst($invoice->status) }}</td>
                                <td>{{ $metodeName }}</td>
                            @else
                                <td></td>
                                <td></td>
                                <td></td>
                            @endif
                        </tr>
                    @endforeach
                @else
                    <tr>
                        <td>{{ $invoice->tanggal_order->format('d M Y') }}</td>
                        <td>{{ $invoice->tanggal_menerima?->format('d M Y') ?? '-' }}</td>
                        <td>{{ $invoice->diterima_oleh ?? '-' }}</td>
                        <td>{{ $invoice->no_faktur }}</td>
                        <td colspan="6" style="text-align: center;">Belum ada item</td>
                        <td>0</td>
                        <td>{{ ucfirst($invoice->status) }}</td>
                        <td>{{ $metodeName }}</td>
                    </tr>
                @endif
            @endforeach
            <tr>
                <td colspan="10" style="text-align: right; font-weight: bold; background-color: #fef08a;">Subtotal Bulan {{ $month['name'] }}:</td>
                <td style="font-weight: bold; background-color: #fef08a;">{{ $month['total'] }}</td>
                <td colspan="2" style="background-color: #fef08a;"></td>
            </tr>
        @empty
            <tr>
                <td colspan="13" style="text-align: center;">Tidak ada data untuk periode ini.</td>
            </tr>
        @endforelse
        @if(count($monthlyData) > 0)
            <tr>
                <td colspan="13"></td>
            </tr>
            <tr>
                <td colspan="10" style="text-align: right; font-weight: bold; background-color: #f97316; color: #ffffff;">GRAND TOTAL:</td>
                <td style="font-weight: bold; background-color: #f97316; color: #ffffff;">{{ $totalSemua }}</td>
                <td colspan="2" style="background-color: #f97316;"></td>
            </tr>
        @endif
    </tbody>
</table>

<table>
    <tr><td colspan="8">Ringkasan Stok Produk</td></tr>
    <tr><td colspan="8">Periode: {{ $stockSummary['start'] ?? '—' }} s/d {{ $stockSummary['end'] ?? '—' }} (Asia/Jakarta), seluruh shift. Satuan: pcs.</td></tr>
    <tr><td colspan="8">Ditambah berasal dari seluruh restock selama periode. Pcs Invoice menjumlahkan pcs yang sudah diisi pada invoice hasil ekspor, termasuk arsip, tanpa menambah stok lagi. Pcs yang belum diisi tidak ikut dijumlahkan.</td></tr>
    <tr>
        <th>Produk</th><th>Pcs Invoice</th><th>Stok Awal</th><th>Ditambah</th><th>Jumlah Stok</th><th>Terjual</th><th>Stok Akhir</th><th>Operasional</th>
    </tr>
    @forelse ($stockSummary['rows'] as $stock)
        <tr>
            <td>{{ $stock['name'] }}</td>
            <td>{{ $stock['invoice_pcs'] ?? '—' }}</td>
            <td>{{ $stock['opening'] ?? '—' }}</td>
            <td>{{ $stock['added'] }}</td>
            <td>{{ $stock['available'] ?? '—' }}</td>
            <td>{{ $stock['sold'] }}</td>
            <td>{{ $stock['closing'] ?? '—' }}</td>
            <td>{{ $stock['operational'] }}</td>
        </tr>
    @empty
        <tr><td colspan="8">Tidak ada produk terpetakan pada invoice hasil ekspor.</td></tr>
    @endforelse
    @if ($stockSummary['unmapped'])
        <tr><td colspan="8">{{ $stockSummary['unmapped'] }} item belum dipetakan ke master produk. Lengkapi melalui halaman edit invoice agar masuk ringkasan stok.</td></tr>
    @endif
    @foreach ($stockSummary['notes'] as $note)
        <tr><td colspan="8">{{ $note }}</td></tr>
    @endforeach
</table>
