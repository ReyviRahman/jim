<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <title>Invoice Membership #{{ $membership->id }}</title>
    <style>
        * { box-sizing: border-box; }
        @page { margin: 28px 32px; }
        body { font-family: 'DejaVu Sans', sans-serif; color: #24313b; font-size: 10px; line-height: 1.45; }
        .header { border-bottom: 2px solid #1f6f5f; padding-bottom: 14px; margin-bottom: 20px; }
        .brand { color: #1f6f5f; font-size: 22px; font-weight: bold; letter-spacing: 1px; }
        .subtitle { color: #64748b; font-size: 10px; margin-top: 2px; }
        .invoice-title { float: right; text-align: right; margin-top: -38px; }
        .invoice-title strong { display: block; font-size: 16px; color: #1f2937; }
        .invoice-title span { color: #64748b; }
        .clear { clear: both; }
        .section { margin-top: 18px; }
        .section-title { color: #1f6f5f; font-size: 11px; font-weight: bold; margin-bottom: 7px; text-transform: uppercase; }
        .info-table, .payment-table, .summary-table { width: 100%; border-collapse: collapse; }
        .info-table td { width: 50%; padding: 4px 8px 4px 0; vertical-align: top; }
        .label { color: #64748b; display: inline-block; min-width: 92px; }
        .payment-table th, .payment-table td { border: 1px solid #d8e0e5; padding: 7px 8px; }
        .payment-table th { background: #edf7f4; color: #245c52; font-size: 9px; text-align: left; }
        .payment-table .amount { text-align: right; }
        .summary { width: 48%; margin-left: auto; margin-top: 18px; }
        .summary-table td { padding: 5px 8px; border-bottom: 1px solid #e5e7eb; }
        .summary-table td:last-child { text-align: right; }
        .summary-table .total td { background: #1f6f5f; color: #fff; border: 0; font-size: 12px; font-weight: bold; padding: 9px 8px; }
        .summary-table .balance td { color: #b45309; font-weight: bold; }
        .footer { position: fixed; bottom: -12px; left: 0; right: 0; text-align: center; color: #64748b; font-size: 8px; }
        .badge { color: #1f6f5f; font-weight: bold; }
    </style>
</head>
<body>
    <div class="header">
        <div class="brand">FRANS GYM</div>
        <div class="subtitle">Invoice Membership</div>
        <div class="invoice-title">
            <strong>INVOICE</strong>
            <span>Membership #{{ $membership->id }}</span><br>
            <span>Dibuat {{ $membership->created_at->locale('id')->translatedFormat('d F Y') }}</span>
        </div>
        <div class="clear"></div>
    </div>

    <div class="section">
        <div class="section-title">Data Member</div>
        <table class="info-table">
            <tr>
                <td><span class="label">Pembayar</span>{{ $membership->user?->name ?? '-' }}</td>
                <td><span class="label">No. HP</span>{{ $membership->user?->phone ?? '-' }}</td>
            </tr>
            @if($members->count() > 1)
                <tr>
                    <td colspan="2"><span class="label">Anggota</span>{{ $members->pluck('name')->join(', ') }}</td>
                </tr>
            @endif
            <tr>
                <td colspan="2"><span class="label">Admin/Kasir</span>{{ $membership->admin?->name ?? '-' }}</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Paket dan Masa Aktif</div>
        <table class="info-table">
            @if($membership->gymPackage)
                <tr>
                    <td><span class="label">Paket Gym</span>{{ $membership->gymPackage->name }}</td>
                    <td><span class="label">Gym s/d</span>{{ $membership->membership_end_date?->locale('id')->translatedFormat('d F Y') ?? '-' }}</td>
                </tr>
            @endif
            @if($membership->ptPackage)
                <tr>
                    <td><span class="label">Paket PT</span>{{ $membership->ptPackage->name }}{{ $membership->total_sessions ? ' ('.$membership->total_sessions.' sesi)' : '' }}</td>
                    <td><span class="label">PT s/d</span>{{ $membership->pt_end_date?->locale('id')->translatedFormat('d F Y') ?? '-' }}</td>
                </tr>
            @endif
            <tr>
                <td><span class="label">Mulai</span>{{ $membership->start_date?->locale('id')->translatedFormat('d F Y') ?? '-' }}</td>
                <td><span class="label">Status</span><span class="badge">{{ strtoupper($membership->payment_status) }}</span></td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Riwayat Pembayaran</div>
        <table class="payment-table">
            <thead>
                <tr>
                    <th>No. Invoice</th>
                    <th>Tanggal</th>
                    <th>Metode</th>
                    <th>Keterangan</th>
                    <th class="amount">Nominal</th>
                </tr>
            </thead>
            <tbody>
                @forelse($membership->transactions as $transaction)
                    <tr>
                        <td>{{ $transaction->invoice_number }}</td>
                        <td>{{ $transaction->payment_date?->locale('id')->translatedFormat('d M Y') ?? '-' }}</td>
                        <td>{{ strtoupper($transaction->payment_method) }}</td>
                        <td>{{ $transaction->transaction_type ?: ($transaction->notes ?: '-') }}</td>
                        <td class="amount">Rp {{ number_format($transaction->amount, 0, ',', '.') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" style="text-align: center; color: #64748b;">Belum ada riwayat pembayaran.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="summary">
        <table class="summary-table">
            <tr><td>Harga Paket</td><td>Rp {{ number_format($membership->base_price, 0, ',', '.') }}</td></tr>
            <tr><td>Diskon</td><td>- Rp {{ number_format($membership->discount_applied, 0, ',', '.') }}</td></tr>
            <tr class="total"><td>Total Tagihan</td><td>Rp {{ number_format($membership->price_paid, 0, ',', '.') }}</td></tr>
            <tr><td>Total Dibayar</td><td>Rp {{ number_format($membership->total_paid, 0, ',', '.') }}</td></tr>
            <tr class="balance"><td>Sisa Tagihan</td><td>Rp {{ number_format($remainingBalance, 0, ',', '.') }}</td></tr>
        </table>
    </div>

    <div class="footer">Invoice ini dibuat secara otomatis oleh sistem FRANS GYM pada {{ now()->locale('id')->translatedFormat('d F Y H:i') }} WIB.</div>
</body>
</html>
