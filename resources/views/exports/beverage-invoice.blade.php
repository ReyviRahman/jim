<table>
    <thead>
        <tr>
            <th>Tanggal Order</th>
            <th>Tanggal Menerima</th>
            <th>Diterima Oleh</th>
            <th>No Faktur</th>
            <th>Nama Barang</th>
            <th>Qty</th>
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
                <td colspan="12"></td>
            </tr>
            @endif
            <tr>
                <td colspan="12" style="background-color: #e5e7eb; font-weight: bold;">Bulan {{ $month['name'] }}</td>
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
                        <td colspan="5" style="text-align: center;">Belum ada item</td>
                        <td>0</td>
                        <td>{{ ucfirst($invoice->status) }}</td>
                        <td>{{ $metodeName }}</td>
                    </tr>
                @endif
            @endforeach
            <tr>
                <td colspan="9" style="text-align: right; font-weight: bold; background-color: #fef08a;">Subtotal Bulan {{ $month['name'] }}:</td>
                <td style="font-weight: bold; background-color: #fef08a;">{{ $month['total'] }}</td>
                <td colspan="2" style="background-color: #fef08a;"></td>
            </tr>
        @empty
            <tr>
                <td colspan="12" style="text-align: center;">Tidak ada data untuk periode ini.</td>
            </tr>
        @endforelse
        @if(count($monthlyData) > 0)
            <tr>
                <td colspan="12"></td>
            </tr>
            <tr>
                <td colspan="9" style="text-align: right; font-weight: bold; background-color: #f97316; color: #ffffff;">GRAND TOTAL:</td>
                <td style="font-weight: bold; background-color: #f97316; color: #ffffff;">{{ $totalSemua }}</td>
                <td colspan="2" style="background-color: #f97316;"></td>
            </tr>
        @endif
    </tbody>
</table>
