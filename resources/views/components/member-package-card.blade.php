@props(['summary', 'first' => false])

<article {{ $attributes->merge(['class' => 'member-package']) }} data-testid="owned-package-{{ $summary['id'] }}" aria-labelledby="package-title-{{ $summary['id'] }}">
    <header class="member-package-hero relative isolate overflow-hidden bg-black text-white sm:rounded-t-3xl">
        <picture class="absolute inset-0 -z-20">
            <source media="(max-width: 1023px)" srcset="{{ asset('member-package-hero-mobile.webp') }}" type="image/webp">
            <img src="{{ asset('member-package-hero.webp') }}" alt="Pelatih mendampingi latihan dumbbell di Frans Gym" class="member-package-photo size-full object-cover" loading="{{ $first ? 'eager' : 'lazy' }}" @if ($first) fetchpriority="high" @endif>
        </picture>
        <div class="member-package-shade absolute inset-0 -z-10" aria-hidden="true"></div>
        <div class="flex items-start justify-between gap-4 px-5 pt-6 sm:px-9 sm:pt-8">
            <div>
                <p class="text-base font-extrabold sm:text-2xl">{{ $summary['has_pt'] ? 'Data Absen PT Client' : 'Data Membership' }}</p>
                <p class="mt-1 text-[11px] text-white/70 sm:text-sm">Detail paket dan kehadiran {{ $summary['has_pt'] ? 'client' : 'member' }}</p>
            </div>
            <div class="shrink-0 text-right" aria-label="Frans Gym Fitness Jambi">
                <p class="text-sm font-black tracking-tight text-brand sm:text-2xl">FRANSGYM</p>
                <p class="mt-0.5 text-[6px] tracking-[0.3em] sm:text-[8px]">FITNESS JAMBI</p>
            </div>
        </div>
        <div class="member-package-intro px-5 pb-20 pt-10 sm:px-9 sm:pb-24 sm:pt-14">
            <div class="member-package-copy">
                <p class="inline-block rounded-full bg-brand px-3 py-1.5 text-[9px] font-extrabold tracking-[0.16em] text-black sm:px-5 sm:text-xs">{{ $summary['label'] }}</p>
                <h2 id="package-title-{{ $summary['id'] }}" class="mt-3 text-balance text-[clamp(1.65rem,4vw,3.5rem)] font-black leading-[1.07] tracking-tight">{{ $summary['name'] }}</h2>
                <p class="mt-3 text-xs font-bold tracking-[0.14em] sm:text-lg">{{ $summary['has_pt'] ? $summary['total_sessions'].' SESI' : 'MEMBERSHIP GYM' }}</p>
                <span class="mt-4 block h-1 w-12 rounded-full bg-brand" aria-hidden="true"></span>
                <p class="mt-4 max-w-60 text-xs leading-relaxed text-white/85 sm:text-base">{{ $summary['has_pt'] ? 'Latihan lebih terarah, hasil lebih maksimal.' : 'Ruang untuk bergerak, semangat untuk lebih kuat.' }}</p>
                <p class="mt-2 text-[10px] text-white/65 sm:text-xs">Mulai {{ $summary['starting_price'] }}</p>
                <div class="mt-6 grid grid-cols-3 divide-x divide-white/20 text-[9px] leading-snug sm:mt-8 sm:text-xs">
                    <div class="pr-2"><x-member-package-icon name="dumbbell" class="mb-2 size-6 text-brand sm:size-8"/>{{ $summary['has_pt'] ? 'Program' : 'Fasilitas' }}<br>{{ $summary['has_pt'] ? 'Terarah' : 'Lengkap' }}</div>
                    <div class="px-2 sm:px-4"><x-member-package-icon name="chart" class="mb-2 size-6 text-brand sm:size-8"/>{{ $summary['has_pt'] ? 'Progress' : 'Latihan' }}<br>{{ $summary['has_pt'] ? 'Terukur' : 'Konsisten' }}</div>
                    <div class="pl-2 sm:pl-4"><x-member-package-icon name="person" class="mb-2 size-6 text-brand sm:size-8"/>{{ $summary['has_pt'] ? 'Didampingi' : 'Suasana' }}<br>{{ $summary['has_pt'] ? 'Coach Profesional' : 'Nyaman' }}</div>
                </div>
            </div>
        </div>
    </header>

    <div class="relative z-10 -mt-12 space-y-3 px-3 sm:space-y-4 sm:px-7">
        <section class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-black/[0.03] sm:rounded-3xl sm:p-7" aria-label="Rincian Harga">
            <dl class="grid grid-cols-2 divide-x divide-gray-200">
                <div class="flex min-w-0 items-center gap-3 pr-3 sm:gap-5 sm:pr-6">
                    <span class="hidden size-16 shrink-0 items-center justify-center rounded-full bg-yellow-50 lg:flex"><x-member-package-icon name="tag" class="size-8"/></span>
                    <div class="min-w-0"><dt class="text-[11px] font-semibold text-gray-500 sm:text-base">Harga Paket</dt><dd class="member-package-price mt-1 font-extrabold tracking-tight text-black">{{ $summary['price'] }}</dd></div>
                </div>
                <div class="flex min-w-0 items-center gap-3 pl-3 sm:gap-5 sm:pl-6">
                    <span class="hidden size-16 shrink-0 items-center justify-center rounded-full bg-yellow-50 lg:flex"><x-member-package-icon name="payment" class="size-8"/></span>
                    <div class="min-w-0"><dt class="text-[11px] font-semibold text-gray-500 sm:text-base">Total Pembayaran</dt><dd class="member-package-price mt-1 font-extrabold tracking-tight text-black">{{ $summary['total'] }}</dd></div>
                </div>
            </dl>
            @if ($summary['discount_amount'] > 0)
                <p class="mt-4 border-t border-gray-100 pt-3 text-xs font-medium text-gray-600">Diskon <span class="float-right text-emerald-700">-{{ $summary['discount'] }}</span></p>
            @endif
        </section>

        <section class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-black/[0.03] sm:rounded-3xl sm:p-7" aria-label="Status dan penggunaan paket">
            <dl class="grid grid-cols-2 divide-x divide-gray-200">
                <div class="flex min-w-0 items-center gap-4 pr-3 sm:pr-6">
                    <span class="hidden size-16 shrink-0 items-center justify-center rounded-full bg-gray-100 sm:flex"><x-member-package-icon name="calendar" class="size-8"/></span>
                    <div class="min-w-0"><dt class="text-xs font-semibold text-gray-500 sm:text-base">{{ $summary['has_pt'] ? 'Jumlah Sesi' : 'Masa Berlaku' }}</dt><dd class="mt-2 text-lg font-bold text-black sm:text-2xl">{{ $summary['has_pt'] ? $summary['total_sessions'].' Sesi' : $summary['gym_end'] }}</dd></div>
                </div>
                <div class="flex items-center gap-4 pl-4 sm:pl-7">
                    <span class="hidden size-16 shrink-0 items-center justify-center rounded-full bg-gray-100 sm:flex"><x-member-package-icon name="person" class="size-8"/></span>
                    <div><dt class="text-xs font-semibold text-gray-500 sm:text-base">Status Paket</dt><dd class="mt-2 inline-flex items-center gap-2 rounded-full bg-brand px-4 py-1.5 text-sm font-bold text-black"><span class="size-2.5 rounded-full bg-emerald-500" aria-hidden="true"></span>Aktif</dd></div>
                </div>
            </dl>

            @if ($summary['has_pt'])
                <div class="mt-5 flex gap-4 border-t border-gray-100 pt-5 sm:gap-7">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center justify-between gap-2 text-xs sm:text-sm"><h3 class="font-bold">Progress Kehadiran</h3><p>{{ $summary['attended_sessions'] }} / {{ $summary['total_sessions'] }} Sesi</p></div>
                        <div class="mt-3 flex items-center gap-3">
                            <progress value="{{ $summary['progress'] }}" max="100" class="member-package-progress h-2.5 w-full sm:h-3" aria-label="Progress kehadiran {{ $summary['name'] }}">{{ $summary['progress'] }}%</progress>
                            <span class="text-sm font-bold">{{ $summary['progress'] }}%</span>
                        </div>
                    </div>
                    <div class="shrink-0 border-l border-gray-200 pl-4 text-center sm:min-w-28 sm:pl-7"><p class="text-[10px] text-gray-500 sm:text-xs">Sisa Sesi</p><p class="mt-1 text-3xl font-extrabold leading-[1.1] sm:text-4xl">{{ $summary['remaining_sessions'] }}</p><p class="mt-1 text-[10px] text-gray-500">Sesi</p></div>
                </div>
                <p class="mt-4 text-xs leading-5 text-gray-500">PT: <span class="font-semibold {{ $summary['pt_active'] ? 'text-gray-700' : 'text-red-700' }}">{{ $summary['pt_active'] ? 'Aktif hingga' : 'Berakhir pada' }} {{ $summary['pt_end'] }}</span></p>
            @endif

            @if ($summary['has_gym'])
                <div class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 pt-4 text-xs sm:text-sm">
                    <p class="text-gray-500">Gym: <span class="font-semibold {{ $summary['gym_active'] ? 'text-gray-700' : 'text-red-700' }}">{{ $summary['gym_active'] ? 'Aktif hingga' : 'Berakhir pada' }} {{ $summary['gym_end'] }}</span></p>
                    <p class="font-bold text-black">{{ $summary['gym_duration'] }}</p>
                </div>
            @endif
        </section>

        <a href="{{ route('member.kehadiran.index') }}" wire:navigate class="flex min-h-14 items-center justify-center gap-4 rounded-xl bg-brand px-4 py-4 text-sm font-extrabold text-black transition-colors hover:bg-yellow-300 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-black sm:min-h-18 sm:rounded-2xl sm:text-lg">
            <x-member-package-icon name="chart" class="size-6 sm:size-8"/>Lihat Riwayat Absen<x-member-package-icon name="chevron" class="size-5 sm:ml-4 sm:size-6"/>
        </a>
        <aside class="flex flex-col gap-4 rounded-2xl bg-gray-100 px-5 py-5 text-gray-500 sm:flex-row sm:items-center sm:gap-6" aria-label="Catatan paket">
            <div class="flex flex-1 items-start gap-3"><x-member-package-icon name="info" class="mt-0.5 size-5 shrink-0"/><div><h3 class="text-xs font-bold text-black sm:text-sm">Catatan</h3><p class="mt-1 text-[11px] leading-relaxed sm:text-xs">{{ $summary['has_pt'] ? 'Pastikan melakukan absensi sesuai jadwal bersama coach. Progress mengikuti kehadiran yang telah tercatat.' : 'Lakukan check-in saat datang dan check-out setelah latihan. Pantau masa aktif membership Anda di sini.' }}</p></div></div>
            <div class="flex items-center gap-3 border-t border-gray-200 pt-3 sm:w-64 sm:border-t-0 sm:border-l sm:pt-0 sm:pl-6"><x-member-package-icon name="dumbbell" class="size-8 shrink-0"/><div><p class="text-[11px] leading-relaxed sm:text-xs">Konsistensi hari ini,<br>hasil yang lebih baik esok.</p><span class="mt-2 block h-1 w-9 rounded-full bg-brand" aria-hidden="true"></span></div></div>
        </aside>
    </div>
</article>
