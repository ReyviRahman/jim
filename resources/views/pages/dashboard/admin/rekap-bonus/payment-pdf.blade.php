<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <title>Pembayaran Bonus #{{ $payment->id }}</title>
    <style>
        @page { margin: 24px 28px 34px; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: 'DejaVu Sans', sans-serif; font-size: 8px; color: #2f332f; }
        .header { width: 100%; border-bottom: 2px solid #166534; margin-bottom: 12px; padding-bottom: 10px; }
        .header td { border: 0; padding: 0; vertical-align: middle; }
        .logo { width: 44px; }
        h1 { margin: 0; font-size: 17px; text-align: center; color: #14532d; }
        .subtitle { margin-top: 3px; text-align: center; color: #5f685f; }
        .meta { width: 100%; margin-bottom: 12px; border-collapse: collapse; }
        .meta td { width: 25%; border: 1px solid #d6ddd6; padding: 6px 8px; vertical-align: top; }
        .label { display: block; margin-bottom: 2px; color: #6b7280; font-size: 7px; }
        .value { font-weight: bold; color: #1f2937; }
        .items { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .items thead { display: table-header-group; }
        .items tr { page-break-inside: avoid; }
        .items th, .items td { border: 1px solid #ccd5cc; padding: 4px 5px; overflow-wrap: break-word; vertical-align: top; }
        .items th { background: #e8f2e8; color: #214b2a; font-size: 7px; text-transform: uppercase; }
        .number { width: 5%; text-align: center; }
        .member { width: 23%; }
        .package { width: 25%; }
        .money { width: 17%; text-align: right; }
        .date { width: 13%; text-align: center; }
        .summary { width: 100%; margin-top: 12px; border-collapse: collapse; page-break-inside: avoid; }
        .summary td { border: 1px solid #d6ddd6; padding: 7px 9px; }
        .summary-label { width: 72%; text-align: right; font-weight: bold; }
        .summary-value { width: 28%; text-align: right; font-weight: bold; }
        .deduction { color: #b91c1c; }
        .net { background: #dcfce7; color: #166534; font-size: 10px; }
        .note { margin-top: 8px; padding: 8px 10px; border: 1px solid #d6ddd6; background: #f8faf8; page-break-inside: avoid; white-space: pre-wrap; overflow-wrap: break-word; }
        .spelled { margin-top: 8px; font-size: 9px; font-style: italic; color: #3f4a3f; }
        .footer { position: fixed; right: 0; bottom: -22px; left: 0; text-align: center; color: #777; font-size: 7px; }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td style="width: 56px;"><img class="logo" src="{{ public_path('icon.png') }}" alt="Logo"></td>
            <td>
                <h1>DETAIL PEMBAYARAN BONUS #{{ $payment->id }}</h1>
                <div class="subtitle">Snapshot pembayaran bonus {{ $payment->staffUser?->name ?? '-' }}</div>
            </td>
            <td style="width: 56px;"></td>
        </tr>
    </table>

    <table class="meta">
        <tr>
            <td>
                <span class="label">Penerima Bonus</span>
                <span class="value">{{ $payment->staffUser?->name ?? '-' }}</span>
            </td>
            <td>
                <span class="label">Periode</span>
                <span class="value">
                    @if($payment->date_start->equalTo($payment->date_end))
                        {{ $payment->date_start->translatedFormat('d F Y') }}
                    @else
                        {{ $payment->date_start->translatedFormat('d F Y') }} - {{ $payment->date_end->translatedFormat('d F Y') }}
                    @endif
                </span>
            </td>
            <td>
                <span class="label">Dibayar Oleh</span>
                <span class="value">{{ $payment->paidBy?->name ?? '-' }}</span>
            </td>
            <td>
                <span class="label">Tanggal Bayar</span>
                <span class="value">{{ $payment->paid_at?->translatedFormat('d F Y H:i') ?? '-' }}</span>
            </td>
        </tr>
        <tr>
            <td colspan="4">
                <span class="label">Search Halaman Saat Pembayaran</span>
                <span class="value">{{ $payment->search_filter ?: 'Semua member' }}</span>
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th class="number">No</th>
                <th class="member">Nama Member</th>
                <th class="package">Paket Membership</th>
                <th class="money">Nominal</th>
                <th class="money">Nominal Akhir</th>
                <th class="date">Tanggal Bayar</th>
            </tr>
        </thead>
        <tbody>
            @forelse($payment->items as $item)
                <tr>
                    <td class="number">{{ $loop->iteration }}</td>
                    <td>{{ $item->member_name }}</td>
                    <td>{{ $item->package_name ?: '-' }}</td>
                    <td class="money">Rp {{ number_format((float) $item->nominal, 0, ',', '.') }}</td>
                    <td class="money">Rp {{ number_format((float) $item->nominal_akhir, 0, ',', '.') }}</td>
                    <td class="date">{{ $item->payment_date?->translatedFormat('d/m/Y') ?? '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" style="text-align: center;">Tidak ada item pembayaran.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <table class="summary">
        <tr>
            <td class="summary-label">Total Keseluruhan</td>
            <td class="summary-value">Rp {{ number_format((float) $payment->total_nominal_akhir, 0, ',', '.') }}</td>
        </tr>
        <tr>
            <td class="summary-label">
                Bonus ({{ number_format((float) $payment->bonus_percentage, 0, ',', '.') }}%) — Rentang:
                @if(strtolower((string) $payment->range_start) === 'min')
                    ≤ Rp {{ number_format((float) $payment->range_end, 0, ',', '.') }}
                @elseif(strtolower((string) $payment->range_end) === 'plus')
                    ≥ Rp {{ number_format((float) $payment->range_start, 0, ',', '.') }}
                @else
                    Rp {{ number_format((float) $payment->range_start, 0, ',', '.') }} - Rp {{ number_format((float) $payment->range_end, 0, ',', '.') }}
                @endif
            </td>
            <td class="summary-value">Rp {{ number_format((float) $payment->bonus_amount, 0, ',', '.') }}</td>
        </tr>
        <tr>
            <td class="summary-label deduction">Potongan</td>
            <td class="summary-value deduction">- Rp {{ number_format((float) $payment->potongan, 0, ',', '.') }}</td>
        </tr>
        <tr class="net">
            <td class="summary-label">BERSIH DITERIMA</td>
            <td class="summary-value">Rp {{ number_format((float) $payment->net_amount, 0, ',', '.') }}</td>
        </tr>
    </table>

    <div class="note"><strong>Keterangan Potongan:</strong> {{ $payment->keterangan_potongan ?: '-' }}</div>
    <p class="spelled">Terbilang: {{ $terbilang }} rupiah</p>

    <div class="footer">Dokumen ini dibuat dari snapshot pembayaran bonus yang tersimpan.</div>
</body>
</html>
