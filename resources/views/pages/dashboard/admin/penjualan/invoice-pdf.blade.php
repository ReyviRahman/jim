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
        .invoice-meta .meta-value { font-weight: bold; overflow-wrap: anywhere; }
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
        .section-mark img { display: block; width: 27px; height: 27px; }
        .section-title { font-size: 11px; font-weight: bold; text-transform: uppercase; }
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
        .payment-section { margin-top: 22px; }
        .payment-table {
            width: 100%;
            table-layout: fixed;
            border: 1px solid #b9b9b9;
        }
        .payment-table th {
            padding: 8px 7px;
            color: #ffe100;
            background: #050505;
            border-right: 1px solid #4a4a4a;
            font-size: 7px;
            text-align: center;
            text-transform: uppercase;
        }
        .payment-table td {
            padding: 9px 7px;
            border-right: 1px solid #dedede;
            border-bottom: 1px solid #dedede;
            vertical-align: top;
            overflow-wrap: anywhere;
        }
        .payment-table .description { width: 43%; }
        .payment-table .date { width: 18%; text-align: center; }
        .payment-table .method { width: 15%; text-align: center; }
        .payment-table .amount { width: 24%; text-align: right; }
        .payment-table .total-label,
        .payment-table .total-amount {
            color: #111111;
            background: #ffe100;
            font-size: 10px;
            font-weight: bold;
        }
        .payment-table .total-label { text-align: right; }
        .payment-table .total-amount { text-align: right; }
        .notes {
            margin-top: 14px;
            padding: 9px 11px;
            border-left: 4px solid #ffe100;
            background: #f7f7f7;
            page-break-inside: avoid;
        }
        .notes-title { margin-bottom: 3px; font-weight: bold; text-transform: uppercase; }
        .terms {
            position: relative;
            margin-top: 18px;
            padding: 11px 15px 10px;
            border: 1px solid #a9a9a9;
            border-radius: 5px;
            page-break-inside: avoid;
            overflow: hidden;
        }
        .terms-title { margin-bottom: 2px; font-size: 11px; font-weight: bold; text-transform: uppercase; }
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
            position: absolute;
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
        .thanks { color: #ffe100; font-size: 20px; font-style: italic; line-height: 1; }
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
        .footer-summary { width: 100%; }
        .footer-summary td { padding: 1px 0; }
        .footer-summary .label { width: 82px; color: #ffe100; }
        .footer-summary .separator { width: 12px; text-align: center; }
        .footer-summary .value { font-weight: bold; overflow-wrap: anywhere; }
        .generated {
            padding: 4px 20px;
            color: #111111;
            background: #ffe100;
            text-align: center;
            font-size: 7px;
        }
        .payment-proof-page {
            padding: 28px 34px 18px;
            break-before: page;
            page-break-before: always;
        }
        .payment-proof-heading {
            width: 100%;
            color: #ffffff;
            background: #050505;
        }
        .payment-proof-heading td { padding: 13px 16px; vertical-align: middle; }
        .payment-proof-heading .title {
            color: #ffe100;
            font-size: 15px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .payment-proof-heading .meta {
            text-align: right;
            font-size: 7px;
            line-height: 1.5;
        }
        .payment-proof-frame {
            width: 100%;
            margin-top: 16px;
            border: 1px solid #b9b9b9;
            page-break-inside: avoid;
        }
        .payment-proof-frame td {
            height: 680px;
            padding: 14px;
            text-align: center;
            vertical-align: middle;
        }
        .payment-proof-image {
            display: block;
            width: auto;
            height: auto;
            max-width: 100%;
            max-height: 650px;
            margin: 0 auto;
        }
        .payment-proof-caption {
            width: 100%;
            margin-top: 8px;
            color: #555555;
            font-size: 7px;
        }
        .payment-proof-caption td { text-align: center; }
    </style>
</head>
<body>
    <footer class="footer">
        <div class="footer-main">
            <table class="footer-table">
                <tr>
                    <td class="thanks-cell">
                        <div class="thanks">Terima Kasih!</div>
                        <div class="thanks-copy">Pembayaran Anda telah tercatat dalam sistem FRANS GYM.</div>
                    </td>
                    <td class="qr-cell">
                        <div class="qr-title">Scan untuk verifikasi</div>
                        <img src="{{ $qrCodeDataUri }}" class="qr-image" alt="QR verifikasi invoice">
                        <div class="qr-copy">Verifikasi keaslian invoice</div>
                    </td>
                    <td class="footer-brand-cell">
                        <table class="footer-summary">
                            <tr><td class="label">Invoice No.</td><td class="separator">:</td><td class="value">{{ $invoiceNumber }}</td></tr>
                            <tr><td class="label">Pembayar</td><td class="separator">:</td><td class="value">{{ $membershipTransaction->user?->name ?? '-' }}</td></tr>
                            <tr><td class="label">{{ $detailLabel }}</td><td class="separator">:</td><td class="value">{{ $detailName }}</td></tr>
                            <tr><td class="label">Status</td><td class="separator">:</td><td class="value">{{ $paymentStatusLabel }}</td></tr>
                        </table>
                    </td>
                </tr>
            </table>
        </div>
        <div class="generated">Invoice ini dibuat otomatis oleh Sistem FRANS GYM pada {{ now()->locale('id')->translatedFormat('d F Y H:i') }} WIB.</div>
    </footer>

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
                                <div class="brand-name">FRANSGYM</div>
                                <div class="brand-tagline">NEVER BACK DOWN &middot; STAY DEDICATED</div>
                            </td>
                        </tr>
                    </table>
                </td>
                <td class="invoice-cell">
                    <div class="invoice-heading">INVOICE</div>
                    <table class="invoice-meta">
                        <tr><td class="meta-label">INVOICE NO.</td><td class="meta-separator">:</td><td class="meta-value">{{ $invoiceNumber }}</td></tr>
                        <tr><td class="meta-label">TANGGAL</td><td class="meta-separator">:</td><td class="meta-value">{{ $invoiceDate?->locale('id')->translatedFormat('d F Y') ?? '-' }}</td></tr>
                        <tr><td class="meta-label">STATUS</td><td class="meta-separator">:</td><td><span class="status-badge {{ $paymentStatusClass }}">{{ $paymentStatusLabel }}</span></td></tr>
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
                            <tr><td class="section-mark"><img src="{{ $sectionIcons['member'] }}" alt=""></td><td class="section-title">Data Pembayar</td></tr>
                        </table>
                        <table class="info-table">
                            <tr><td class="info-label">Nama</td><td class="info-separator">:</td><td class="info-value">{{ $membershipTransaction->user?->name ?? '-' }}</td></tr>
                            <tr><td class="info-label">No. HP</td><td class="info-separator">:</td><td class="info-value">{{ $membershipTransaction->user?->phone ?? '-' }}</td></tr>
                            <tr><td class="info-label">Email</td><td class="info-separator">:</td><td class="info-value">{{ $membershipTransaction->user?->email ?? '-' }}</td></tr>
                            <tr><td class="info-label">ID Member</td><td class="info-separator">:</td><td class="info-value">{{ $memberNumber }}</td></tr>
                            <tr><td class="info-label">Admin / Kasir</td><td class="info-separator">:</td><td class="info-value">{{ $membershipTransaction->admin?->name ?? '-' }}</td></tr>
                        </table>
                        @if($isMembershipTransaction && $members->count() > 1)
                            <div class="members-note"><strong>Anggota:</strong> {{ $members->pluck('name')->join(', ') }}</div>
                        @endif
                    </section>
                </td>
                <td class="column-right">
                    <section class="section">
                        <table class="section-heading">
                            <tr><td class="section-mark"><img src="{{ $sectionIcons['membership'] }}" alt=""></td><td class="section-title">{{ $detailHeading }}</td></tr>
                        </table>
                        <table class="info-table">
                            <tr><td class="info-label">Jenis Transaksi</td><td class="info-separator">:</td><td class="info-value">{{ $membershipTransaction->transaction_type ?: '-' }}</td></tr>
                            <tr><td class="info-label">{{ $detailLabel }}</td><td class="info-separator">:</td><td class="info-value">{{ $detailName }}</td></tr>
                            <tr><td class="info-label">Metode Pembayaran</td><td class="info-separator">:</td><td class="info-value">{{ $paymentMethod }}</td></tr>
                            @if($isMembershipTransaction)
                                <tr><td class="info-label">Tanggal Mulai</td><td class="info-separator">:</td><td class="info-value">{{ $membershipTransaction->start_date?->locale('id')->translatedFormat('d F Y') ?? '-' }}</td></tr>
                                <tr><td class="info-label">Tanggal Berakhir</td><td class="info-separator">:</td><td class="info-value">{{ $membershipTransaction->end_date?->locale('id')->translatedFormat('d F Y') ?? '-' }}</td></tr>
                            @endif
                        </table>
                    </section>
                </td>
            </tr>
        </table>

        <section class="payment-section section">
            <table class="section-heading">
                <tr><td class="section-mark"><img src="{{ $sectionIcons['payment'] }}" alt=""></td><td class="section-title">Rincian Pembayaran</td></tr>
            </table>
            <table class="payment-table">
                <thead>
                    <tr>
                        <th class="description">Keterangan</th>
                        <th class="date">Tanggal</th>
                        <th class="method">Metode</th>
                        <th class="amount">Nominal</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>{{ $membershipTransaction->transaction_type ?: '-' }} - {{ $detailName }}</td>
                        <td class="date">{{ $invoiceDate?->locale('id')->translatedFormat('d M Y') ?? '-' }}</td>
                        <td class="method">{{ $paymentMethod }}</td>
                        <td class="amount">Rp {{ number_format($membershipTransaction->amount, 0, ',', '.') }}</td>
                    </tr>
                    <tr>
                        <td colspan="3" class="total-label">TOTAL DIBAYAR</td>
                        <td class="total-amount">Rp {{ number_format($membershipTransaction->amount, 0, ',', '.') }}</td>
                    </tr>
                </tbody>
            </table>
        </section>

        @if($membershipTransaction->notes)
            <section class="notes">
                <div class="notes-title">Catatan</div>
                <div>{{ $membershipTransaction->notes }}</div>
            </section>
        @endif

        @if($isMembershipTransaction)
            <section class="terms">
                <img src="{{ $logoDataUri }}" class="terms-watermark" alt="">
                <div class="terms-title">Ketentuan Pembayaran</div>
                <div class="terms-intro">Dengan melakukan pembayaran, member dianggap telah membaca dan menyetujui seluruh ketentuan FRANS GYM.</div>
                <table class="terms-table">
                    <tr><td class="term-number"><span>1</span></td><td>Seluruh pembayaran yang telah diterima bersifat final, tidak dapat dibatalkan, dikembalikan (<span class="term-highlight">non-refundable</span>), atau dialihkan.</td></tr>
                    <tr><td class="term-number"><span>2</span></td><td>Membership bersifat pribadi dan tidak dapat dipindahtangankan kepada orang lain.</td></tr>
                    <tr><td class="term-number"><span>3</span></td><td>Masa aktif membership berjalan sesuai tanggal yang tercatat pada transaksi ini.</td></tr>
                </table>
            </section>
        @else
            <section class="terms">
                <img src="{{ $logoDataUri }}" class="terms-watermark" alt="">
                <div class="terms-title">Konfirmasi Pembayaran</div>
                <div class="terms-intro">Dokumen ini merupakan bukti bahwa pembayaran di atas telah diterima dan tercatat oleh FRANS GYM.</div>
            </section>
        @endif
    </main>

    @if($paymentProofDataUri)
        <section class="payment-proof-page">
            <table class="payment-proof-heading">
                <tr>
                    <td class="title">Lampiran Bukti Pembayaran</td>
                    <td class="meta">
                        Invoice {{ $invoiceNumber }}<br>
                        Metode {{ $paymentMethod }}
                    </td>
                </tr>
            </table>
            <table class="payment-proof-frame">
                <tr>
                    <td>
                        <img
                            src="{{ $paymentProofDataUri }}"
                            class="payment-proof-image"
                            alt="Bukti pembayaran invoice {{ $invoiceNumber }}"
                        >
                    </td>
                </tr>
            </table>
            <table class="payment-proof-caption">
                <tr><td>Bukti pembayaran terlampir sesuai transaksi yang tercatat pada invoice ini.</td></tr>
            </table>
        </section>
    @endif

</body>
</html>
