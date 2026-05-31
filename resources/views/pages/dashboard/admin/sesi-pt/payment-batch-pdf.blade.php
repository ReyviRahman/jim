<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <title>Detail Pembayaran Sesi PT - {{ $batch->pt?->name ?? 'Unknown' }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 12px; color: #333; padding: 20px; }
        .header { display: flex; align-items: center; border-bottom: 2px solid #333; padding-bottom: 15px; margin-bottom: 20px; }
        .header img { height: 50px; width: auto; }
        .header h2 { flex: 1; text-align: center; font-size: 20px; font-weight: bold; }
        .info { margin-bottom: 20px; font-size: 12px; }
        .info p { margin-bottom: 4px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        table th, table td { border: 1px solid #ccc; padding: 8px 10px; }
        table th { background-color: #f0f0f0; font-weight: bold; text-align: left; }
        table td.text-center { text-align: center; }
        table td.text-right { text-align: right; }
        table tfoot td { background-color: #f0f0f0; font-weight: bold; }
        .footer { margin-top: 10px; font-size: 12px; }
        .footer .bersih { font-size: 14px; font-weight: bold; margin-bottom: 4px; }
        .footer .terbilang { font-style: italic; color: #555; }
    </style>
</head>
<body>
    <div class="header">
        <img src="{{ public_path('icon.png') }}" alt="Icon">
        <h2>DETAIL PEMBAYARAN SESI PT #{{ $batch->id }}</h2>
    </div>

    <div class="info">
        @if($batch->date_start && $batch->date_end)
            <p>
                @if($batch->date_start->equalTo($batch->date_end))
                    {{ $batch->date_start->translatedFormat('d F Y') }}
                @else
                    {{ $batch->date_start->translatedFormat('d F Y') }} - {{ $batch->date_end->translatedFormat('d F Y') }}
                @endif
            </p>
        @endif
        <p><strong>NAMA:</strong> {{ $batch->pt?->name ?? '-' }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th>JENIS</th>
                <th style="text-align: center;">JUMLAH</th>
                <th style="text-align: right;">TOTAL</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row['jenis'] }}</td>
                    <td class="text-center">{{ $row['jumlah'] }}</td>
                    <td class="text-right">Rp {{ number_format($row['total'], 0, ',', '.') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="3" style="text-align: center;">Belum ada data.</td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <td>TOTAL</td>
                <td class="text-center">{{ $grandTotalJumlah }}</td>
                <td class="text-right">Rp {{ number_format($grandTotal, 0, ',', '.') }}</td>
            </tr>
        </tfoot>
    </table>

    <div class="footer">
        <p class="bersih">BERSIH DITERIMA: Rp {{ number_format($grandTotal, 0, ',', '.') }}</p>
        <p class="terbilang">Terbilang: {{ $terbilang }} rupiah</p>
    </div>
</body>
</html>
