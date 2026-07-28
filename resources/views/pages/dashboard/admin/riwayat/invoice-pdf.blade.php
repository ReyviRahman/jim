<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <title>Invoice {{ $invoiceNumber }}</title>
    <style>
        @page { margin: 0 0 150px; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            color: #111111;
            background: #ffffff;
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 8px;
            line-height: 1.35;
        }
        table { border-collapse: collapse; }
        .header {
            position: relative;
            min-height: 126px;
            padding: 24px 34px 20px;
            overflow: hidden;
            color: #ffffff;
            background: #050505;
        }
        .header-accent {
            position: absolute;
            top: -44px;
            right: -42px;
            width: 118px;
            height: 210px;
            background: #ffe100;
            transform: rotate(29deg);
        }
        .header-accent-cut {
            position: absolute;
            top: -20px;
            right: 31px;
            width: 17px;
            height: 180px;
            background: #050505;
            transform: rotate(29deg);
        }
        .header-table { position: relative; width: 100%; z-index: 2; }
        .header-table td { vertical-align: middle; }
        .brand-cell { width: 58%; border-right: 1px solid #ffe100; }
        .invoice-cell { width: 42%; padding-left: 24px; }
        .brand-table { width: 100%; }
        .brand-logo { width: 68px; padding-right: 12px; }
        .brand-logo img { width: 60px; height: 60px; }
        .brand-name {
            color: #ffe100;
            font-size: 27px;
            font-weight: bold;
            font-style: italic;
            letter-spacing: -1px;
            line-height: 1;
        }
        .brand-name span {
            color: #ffe100;
        }
        .brand-tagline {
            margin-top: 5px;
            color: #ffe100;
            font-size: 7px;
            font-weight: bold;
            letter-spacing: 1.5px;
        }
        .invoice-heading {
            margin-bottom: 7px;
            color: #ffe100;
            font-size: 22px;
            font-weight: bold;
            line-height: 1;
        }
        .invoice-meta { width: 100%; font-size: 8px; }
        .invoice-meta td { padding: 2px 0; }
        .invoice-meta .meta-label { width: 66px; font-weight: bold; }
        .invoice-meta .meta-separator { width: 12px; text-align: center; }
        .invoice-meta .meta-value { font-weight: bold; }
        .status-badge {
            display: inline-block;
            min-width: 72px;
            padding: 3px 10px;
            border-radius: 12px;
            text-align: center;
            font-size: 8px;
            font-weight: bold;
        }
        .status-paid { color: #111111; background: #ffe100; }
        .status-partial { color: #111111; background: #ffb800; }
        .status-unpaid { color: #ffffff; background: #c62828; }
        .content { padding: 22px 34px 18px; }
        .two-column { width: 100%; table-layout: fixed; }
        .two-column > tbody > tr > td { width: 50%; vertical-align: top; }
        .column-left { padding-right: 14px; }
        .column-right { padding-left: 14px; border-left: 1px solid #b9b9b9; }
        .section { page-break-inside: avoid; }
        .section-heading {
            width: 100%;
            margin-bottom: 9px;
            border-bottom: 1px solid #b9b9b9;
        }
        .section-heading td { vertical-align: middle; padding-bottom: 5px; }
        .section-mark { width: 34px; }
        .section-mark img {
            display: block;
            width: 27px;
            height: 27px;
        }
        .section-title {
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .info-table { width: 100%; }
        .info-table td { padding: 3px 2px; vertical-align: top; }
        .info-label { width: 39%; }
        .info-separator { width: 5%; text-align: center; }
        .info-value { width: 56%; font-weight: 500; overflow-wrap: anywhere; }
        .members-note {
            margin-top: 6px;
            padding: 6px 7px;
            border-left: 3px solid #ffe100;
            background: #f7f7f7;
        }
        .membership-status {
            display: inline-block;
            padding: 2px 9px;
            border-radius: 8px;
            color: #111111;
            background: #ffe100;
            font-weight: bold;
        }
        .payment-sections { margin-top: 20px; }
        .payment-sections .summary-section { float: left; width: 46%; }
        .payment-sections .history-section { float: right; width: 51%; }
        .payment-sections-long .summary-section { float: none; width: 46%; }
        .payment-sections-long .history-section {
            float: none;
            width: 100%;
            margin-top: 20px;
        }
        .clear { clear: both; }
        .summary-table {
            width: 100%;
            border: 1px solid #b9b9b9;
            border-radius: 4px;
        }
        .summary-table td { padding: 7px 9px; border-bottom: 1px solid #dedede; }
        .summary-table td:last-child { text-align: right; font-weight: bold; }
        .summary-table .total td {
            color: #111111;
            background: #ffe100;
            font-size: 10px;
            font-weight: bold;
        }
        .summary-table .balance td {
            border-bottom: 0;
            color: #ffe100;
            background: #050505;
            font-weight: bold;
        }
        .history-table {
            width: 100%;
            table-layout: fixed;
            border: 1px solid #b9b9b9;
        }
        .history-table thead { display: table-header-group; }
        .history-table tr { page-break-inside: avoid; }
        .history-table th {
            padding: 7px 5px;
            color: #ffe100;
            background: #050505;
            border-right: 1px solid #4a4a4a;
            font-size: 7px;
            text-align: center;
            text-transform: uppercase;
        }
        .history-table td {
            padding: 6px 5px;
            border-right: 1px solid #dedede;
            border-bottom: 1px solid #dedede;
            vertical-align: top;
            overflow-wrap: anywhere;
        }
        .history-table .date { width: 22%; }
        .history-table .method { width: 17%; text-align: center; }
        .history-table .amount { width: 25%; text-align: right; }
        .history-table .description { width: 36%; }
        .history-table .empty { padding: 14px 5px; color: #777777; text-align: center; }
        .terms {
            position: relative;
            margin-top: 20px;
            padding: 11px 15px 10px;
            border: 1px solid #a9a9a9;
            border-radius: 5px;
            page-break-inside: avoid;
            overflow: hidden;
        }
        .terms-title {
            margin-bottom: 2px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .terms-intro { margin-bottom: 6px; font-size: 7px; }
        .terms-table { position: relative; width: 88%; z-index: 2; }
        .terms-table td { padding: 2px 0; vertical-align: top; }
        .term-number { width: 23px; }
        .term-number span {
            display: inline-block;
            width: 14px;
            height: 14px;
            padding-top: 2px;
            border-radius: 50%;
            color: #ffffff;
            background: #050505;
            text-align: center;
            font-size: 7px;
            font-weight: bold;
        }
        .term-highlight { color: #b89c00; font-weight: bold; }
        .terms-watermark {
            position: absolute;
            right: 16px;
            bottom: 8px;
            width: 92px;
            height: 92px;
            opacity: 0.08;
        }
        .footer {
            position: fixed;
            right: 0;
            bottom: -150px;
            left: 0;
            color: #ffffff;
            background: #050505;
            page-break-inside: avoid;
        }
        .footer-main { padding: 16px 34px 13px; }
        .footer-table { width: 100%; table-layout: fixed; }
        .footer-table td { vertical-align: middle; }
        .thanks-cell { width: 34%; padding-right: 18px; border-right: 1px solid #ffe100; }
        .qr-cell {
            width: 22%;
            padding: 0 17px;
            border-right: 1px solid #ffe100;
            text-align: center;
        }
        .footer-brand-cell { width: 44%; padding-left: 20px; }
        .thanks {
            color: #ffe100;
            font-size: 20px;
            font-style: italic;
            line-height: 1;
        }
        .thanks-copy { margin-top: 6px; color: #e5e5e5; font-size: 8px; }
        .qr-title {
            margin-bottom: 4px;
            color: #ffe100;
            font-size: 7px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .qr-image {
            display: block;
            width: 62px;
            height: 62px;
            margin: 0 auto;
            padding: 3px;
            background: #ffffff;
        }
        .qr-copy { margin-top: 3px; color: #d5d5d5; font-size: 6px; }
        .footer-brand-table { width: 100%; }
        .footer-logo { width: 52px; padding-right: 10px; }
        .footer-logo img { width: 44px; height: 44px; }
        .footer-summary { width: 100%; }
        .footer-summary td { padding: 1px 0; }
        .footer-summary .label { width: 92px; color: #ffe100; }
        .footer-summary .separator { width: 12px; text-align: center; }
        .footer-summary .value { font-weight: bold; }
        .generated {
            padding: 4px 20px;
            color: #111111;
            background: #ffe100;
            text-align: center;
            font-size: 7px;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-accent"></div>
        <div class="header-accent-cut"></div>
        <table class="header-table">
            <tr>
                <td class="brand-cell">
                    <table class="brand-table">
                        <tr>
                            <td class="brand-logo"><img src="{{ $logoDataUri }}" alt="Logo FRANS GYM"></td>
                            <td>
                                <div class="brand-name">FRANS<span>GYM</span></div>
                                <div class="brand-tagline">NEVER BACK DOWN &middot; STAY DEDICATED</div>
                            </td>
                        </tr>
                    </table>
                </td>
                <td class="invoice-cell">
                    <div class="invoice-heading">INVOICE</div>
                    <table class="invoice-meta">
                        <tr>
                            <td class="meta-label">INVOICE NO.</td>
                            <td class="meta-separator">:</td>
                            <td class="meta-value">{{ $invoiceNumber }}</td>
                        </tr>
                        <tr>
                            <td class="meta-label">TANGGAL</td>
                            <td class="meta-separator">:</td>
                            <td class="meta-value">{{ $invoiceDate->locale('id')->translatedFormat('d F Y') }}</td>
                        </tr>
                        <tr>
                            <td class="meta-label">STATUS</td>
                            <td class="meta-separator">:</td>
                            <td><span class="status-badge {{ $paymentStatusClass }}">{{ $paymentStatusLabel }}</span></td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </div>

    <main class="content">
        <table class="two-column">
            <tr>
                <td class="column-left">
                    <section class="section">
                        <table class="section-heading">
                            <tr>
                                <td class="section-mark"><img src="{{ $sectionIcons['member'] }}" alt=""></td>
                                <td class="section-title">Data Member</td>
                            </tr>
                        </table>
                        <table class="info-table">
                            <tr><td class="info-label">Nama Member</td><td class="info-separator">:</td><td class="info-value">{{ $membership->user?->name ?? '-' }}</td></tr>
                            <tr><td class="info-label">No. HP</td><td class="info-separator">:</td><td class="info-value">{{ $membership->user?->phone ?? '-' }}</td></tr>
                            <tr><td class="info-label">Email</td><td class="info-separator">:</td><td class="info-value">{{ $membership->user?->email ?? '-' }}</td></tr>
                            <tr><td class="info-label">ID Member</td><td class="info-separator">:</td><td class="info-value">{{ $memberNumber }}</td></tr>
                            <tr><td class="info-label">Admin / Kasir</td><td class="info-separator">:</td><td class="info-value">{{ $membership->admin?->name ?? '-' }}</td></tr>
                        </table>
                        @if($members->count() > 1)
                            <div class="members-note"><strong>Anggota:</strong> {{ $members->pluck('name')->join(', ') }}</div>
                        @endif
                    </section>
                </td>
                <td class="column-right">
                    <section class="section">
                        <table class="section-heading">
                            <tr>
                                <td class="section-mark"><img src="{{ $sectionIcons['membership'] }}" alt=""></td>
                                <td class="section-title">Detail Membership</td>
                            </tr>
                        </table>
                        <table class="info-table">
                            <tr><td class="info-label">Paket</td><td class="info-separator">:</td><td class="info-value">{{ $packageName }}</td></tr>
                            <tr><td class="info-label">Tanggal Mulai</td><td class="info-separator">:</td><td class="info-value">{{ $membership->start_date?->locale('id')->translatedFormat('d F Y') ?? '-' }}</td></tr>
                            <tr><td class="info-label">Tanggal Berakhir</td><td class="info-separator">:</td><td class="info-value">{{ $membershipEndDate }}</td></tr>
                            <tr><td class="info-label">Metode Pembayaran</td><td class="info-separator">:</td><td class="info-value">{{ $paymentMethod }}</td></tr>
                            <tr><td class="info-label">Status Membership</td><td class="info-separator">:</td><td class="info-value"><span class="membership-status">{{ $membershipStatusLabel }}</span></td></tr>
                        </table>
                    </section>
                </td>
            </tr>
        </table>

        <div class="payment-sections {{ $membership->transactions->count() > 5 ? 'payment-sections-long' : '' }}">
            <section class="summary-section section">
                <table class="section-heading">
                    <tr>
                        <td class="section-mark"><img src="{{ $sectionIcons['payment'] }}" alt=""></td>
                        <td class="section-title">Rincian Pembayaran</td>
                    </tr>
                </table>
                <table class="summary-table">
                    <tr><td>Harga Membership</td><td>Rp {{ number_format($membership->base_price, 0, ',', '.') }}</td></tr>
                    <tr><td>Diskon</td><td>- Rp {{ number_format($membership->discount_applied, 0, ',', '.') }}</td></tr>
                    <tr class="total"><td>Total Tagihan</td><td>Rp {{ number_format($membership->price_paid, 0, ',', '.') }}</td></tr>
                    <tr><td>Total Dibayar</td><td>Rp {{ number_format($membership->total_paid, 0, ',', '.') }}</td></tr>
                    <tr class="balance"><td>Sisa Tagihan</td><td>Rp {{ number_format($remainingBalance, 0, ',', '.') }}</td></tr>
                </table>
            </section>
            <section class="history-section">
                <table class="section-heading">
                    <tr>
                        <td class="section-mark"><img src="{{ $sectionIcons['history'] }}" alt=""></td>
                        <td class="section-title">Riwayat Pembayaran</td>
                    </tr>
                </table>
                <table class="history-table">
                    <thead>
                        <tr>
                            <th class="date">Tanggal</th>
                            <th class="method">Metode</th>
                            <th class="amount">Nominal</th>
                            <th class="description">Keterangan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($membership->transactions as $transaction)
                            <tr>
                                <td>{{ $transaction->payment_date?->locale('id')->translatedFormat('d M Y') ?? '-' }}</td>
                                <td class="method">{{ str($transaction->payment_method)->upper() }}</td>
                                <td class="amount">Rp {{ number_format($transaction->amount, 0, ',', '.') }}</td>
                                <td>{{ $transaction->transaction_type ?: ($transaction->notes ?: '-') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="empty">Belum ada riwayat pembayaran.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </section>
            <div class="clear"></div>
        </div>

        <section class="terms">
            <img src="{{ $logoDataUri }}" class="terms-watermark" alt="">
            <div class="terms-title">Ketentuan Pembayaran</div>
            <div class="terms-intro">Dengan melakukan pembayaran, member dianggap telah membaca dan menyetujui seluruh ketentuan FRANS GYM.</div>
            <table class="terms-table">
                <tr><td class="term-number"><span>1</span></td><td>Seluruh pembayaran yang telah diterima oleh FRANS GYM bersifat final, tidak dapat dibatalkan, tidak dapat dikembalikan (<span class="term-highlight">non-refundable</span>), dan tidak dapat dialihkan kepada pihak lain.</td></tr>
                <tr><td class="term-number"><span>2</span></td><td>Membership bersifat pribadi dan tidak dapat dipindahtangankan kepada orang lain.</td></tr>
                <tr><td class="term-number"><span>3</span></td><td>Masa aktif membership tetap berjalan sesuai tanggal yang tertera pada invoice.</td></tr>
                <tr><td class="term-number"><span>4</span></td><td>FRANS GYM tidak bertanggung jawab atas membership yang tidak digunakan oleh member selama masa aktif.</td></tr>
                <tr><td class="term-number"><span>5</span></td><td>Apabila terjadi penutupan operasional karena force majeure, kebijakan akan mengikuti ketentuan manajemen FRANS GYM.</td></tr>
            </table>
        </section>
    </main>

    <footer class="footer">
        <div class="footer-main">
            <table class="footer-table">
                <tr>
                    <td class="thanks-cell">
                        <div class="thanks">Terima Kasih!</div>
                        <div class="thanks-copy">Telah mempercayakan perjalanan fitness Anda bersama FRANS GYM.</div>
                    </td>
                    <td class="qr-cell">
                        <div class="qr-title">Scan untuk verifikasi</div>
                        <img src="{{ $qrCodeDataUri }}" class="qr-image" alt="QR verifikasi invoice">
                        <div class="qr-copy">Verifikasi keaslian invoice</div>
                    </td>
                    <td class="footer-brand-cell">
                        <table class="footer-brand-table">
                            <tr>
                                <td class="footer-logo"><img src="{{ $logoDataUri }}" alt="Logo FRANS GYM"></td>
                                <td>
                                    <table class="footer-summary">
                                        <tr><td class="label">Invoice No.</td><td class="separator">:</td><td class="value">{{ $invoiceNumber }}</td></tr>
                                        <tr><td class="label">Nama Member</td><td class="separator">:</td><td class="value">{{ $membership->user?->name ?? '-' }}</td></tr>
                                        <tr><td class="label">Paket</td><td class="separator">:</td><td class="value">{{ $packageName }}</td></tr>
                                        <tr><td class="label">Status Pembayaran</td><td class="separator">:</td><td class="value">{{ $paymentStatusLabel }}</td></tr>
                                    </table>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </div>
        <div class="generated">Invoice ini dibuat otomatis oleh Sistem FRANS GYM pada {{ now()->locale('id')->translatedFormat('d F Y H:i') }} WIB.</div>
    </footer>
</body>
</html>
