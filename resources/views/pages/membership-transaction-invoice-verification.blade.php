<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifikasi Invoice {{ $invoiceNumber }}</title>
    <link rel="icon" href="{{ asset('icon.png') }}" type="image/png">
    <x-app-fonts />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#080808] font-sans text-white">
    <main class="mx-auto flex min-h-screen max-w-3xl items-center px-4 py-10 sm:px-6">
        <section class="w-full overflow-hidden rounded-2xl border border-yellow-400/40 bg-[#111111] shadow-2xl shadow-yellow-500/10">
            <div class="h-2 bg-brand"></div>

            <div class="p-6 sm:p-10">
                <div class="flex flex-col gap-6 border-b border-white/10 pb-8 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-center gap-4">
                        <img src="{{ asset('icon.png') }}" alt="Logo FRANS GYM" class="size-16 shrink-0 rounded-full">
                        <div>
                            <p class="text-2xl font-black italic tracking-tight text-brand">FRANS GYM</p>
                            <p class="mt-1 text-xs tracking-[0.2em] text-white/60">INVOICE VERIFICATION</p>
                        </div>
                    </div>

                    <div class="inline-flex w-fit items-center gap-2 rounded-full bg-emerald-400 px-4 py-2 text-sm font-bold text-emerald-950">
                        <svg viewBox="0 0 24 24" fill="none" class="size-5" aria-hidden="true">
                            <path d="m5 12 4 4L19 6" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        Invoice Terverifikasi
                    </div>
                </div>

                <div class="py-8">
                    <p class="text-sm text-white/50">Nomor Invoice</p>
                    <h1 class="mt-1 break-all text-2xl font-bold text-brand sm:text-3xl">{{ $invoiceNumber }}</h1>
                    <p class="mt-3 text-sm leading-6 text-white/65">
                        Tanda tangan digital pada URL valid dan data di bawah ini cocok dengan catatan transaksi FRANS GYM.
                    </p>
                </div>

                <dl class="grid gap-px overflow-hidden rounded-xl border border-white/10 bg-white/10 sm:grid-cols-2">
                    <div class="bg-[#171717] p-5">
                        <dt class="text-xs font-medium uppercase tracking-wider text-white/45">Nama Pembayar</dt>
                        <dd class="mt-2 font-semibold">{{ $membershipTransaction->user?->name ?? '-' }}</dd>
                    </div>
                    <div class="bg-[#171717] p-5">
                        <dt class="text-xs font-medium uppercase tracking-wider text-white/45">Jenis Transaksi</dt>
                        <dd class="mt-2 font-semibold">{{ $membershipTransaction->transaction_type ?: '-' }}</dd>
                    </div>
                    <div class="bg-[#171717] p-5">
                        <dt class="text-xs font-medium uppercase tracking-wider text-white/45">{{ $detailLabel }}</dt>
                        <dd class="mt-2 font-semibold">{{ $detailName }}</dd>
                    </div>
                    <div class="bg-[#171717] p-5">
                        <dt class="text-xs font-medium uppercase tracking-wider text-white/45">Tanggal Invoice</dt>
                        <dd class="mt-2 font-semibold">{{ $invoiceDate?->locale('id')->translatedFormat('d F Y') ?? '-' }}</dd>
                    </div>
                    <div class="bg-[#171717] p-5">
                        <dt class="text-xs font-medium uppercase tracking-wider text-white/45">Metode Pembayaran</dt>
                        <dd class="mt-2 font-semibold">{{ $paymentMethod }}</dd>
                    </div>
                    <div class="bg-[#171717] p-5">
                        <dt class="text-xs font-medium uppercase tracking-wider text-white/45">Nominal</dt>
                        <dd class="mt-2 font-bold text-brand">Rp {{ number_format($membershipTransaction->amount, 0, ',', '.') }}</dd>
                    </div>
                    @if($isMembershipTransaction)
                        <div class="bg-[#171717] p-5">
                            <dt class="text-xs font-medium uppercase tracking-wider text-white/45">Tanggal Mulai</dt>
                            <dd class="mt-2 font-semibold">{{ $membershipTransaction->start_date?->locale('id')->translatedFormat('d F Y') ?? '-' }}</dd>
                        </div>
                        <div class="bg-[#171717] p-5">
                            <dt class="text-xs font-medium uppercase tracking-wider text-white/45">Tanggal Berakhir</dt>
                            <dd class="mt-2 font-semibold">{{ $membershipTransaction->end_date?->locale('id')->translatedFormat('d F Y') ?? '-' }}</dd>
                        </div>
                    @endif
                </dl>

                <p class="mt-8 text-center text-xs leading-5 text-white/40">
                    Halaman ini hanya dapat dibuka melalui tautan verifikasi yang ditandatangani sistem FRANS GYM.
                </p>
            </div>
        </section>
    </main>
</body>
</html>
