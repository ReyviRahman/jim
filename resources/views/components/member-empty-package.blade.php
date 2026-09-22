<section {{ $attributes->merge(['class' => 'member-empty-package relative isolate overflow-hidden bg-black px-5 py-8 text-white sm:rounded-3xl sm:px-12 sm:py-12']) }} aria-labelledby="empty-membership-title">
    <img src="{{ asset('ruangan.webp') }}" alt="" class="absolute inset-0 -z-30 size-full object-cover grayscale" aria-hidden="true">
    <div class="absolute inset-0 -z-20 bg-black/80" aria-hidden="true"></div>
    <div class="member-empty-stripe absolute -z-10" aria-hidden="true"></div>
    <header class="flex items-start justify-between gap-4">
        <div>
            <p class="text-2xl font-black italic tracking-tight sm:text-4xl">FRANS<span class="text-brand">GYM</span></p>
            <p class="mt-2 text-[8px] leading-5 tracking-[0.35em] sm:text-[10px]">NEVERBACKDOWN<br>STAYDEDICATED</p>
            <span class="mt-5 block h-1 w-12 rounded-full bg-brand" aria-hidden="true"></span>
        </div>
        <p class="pt-1 text-right text-[8px] leading-5 tracking-[0.25em] sm:text-xs">MEMBERSHIP<br>STATUS<span class="mt-3 ml-auto block h-1 w-8 rounded-full bg-brand" aria-hidden="true"></span></p>
    </header>
    <div class="member-empty-panel relative mx-auto mt-10 max-w-4xl rounded-[2rem] border-2 border-brand px-5 py-9 text-center sm:mt-14 sm:rounded-[3rem] sm:px-12 sm:py-12">
        <span class="member-empty-calendar mx-auto flex size-24 items-center justify-center rounded-full bg-brand text-black sm:size-32">
            <x-member-package-icon name="calendar" class="size-12 sm:size-16"/>
        </span>
        <h2 id="empty-membership-title" class="mt-7 text-balance text-3xl font-extrabold leading-[1.15] tracking-tight sm:mt-9 sm:text-5xl lg:text-6xl">Belum ada <span class="text-brand">membership atau PT</span> aktif</h2>
        <p class="mx-auto mt-5 max-w-2xl text-pretty text-sm leading-relaxed text-gray-300 sm:mt-7 sm:text-xl">Setelah Anda memiliki membership atau PT aktif, informasi akan otomatis ditampilkan di sini.</p>
        <span class="mx-auto mt-6 block h-1 w-12 rounded-full bg-brand" aria-hidden="true"></span>
        <a href="{{ route('home') }}#lokasi" class="mx-auto mt-7 flex min-h-14 max-w-xl items-center justify-center gap-3 rounded-2xl bg-brand px-4 py-4 text-xs font-extrabold text-black transition-colors hover:bg-yellow-300 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-white sm:mt-9 sm:min-h-18 sm:text-lg">
            HUBUNGI ADMIN<x-member-package-icon name="chevron" class="size-5 shrink-0 sm:size-6"/>
        </a>
    </div>
    <footer class="relative mt-10 flex items-center gap-5 sm:mt-12">
        <p class="shrink-0 rounded-lg bg-black/70 px-3 py-2 text-[8px] font-semibold leading-5 tracking-[0.25em] sm:text-[10px]">MORE THAN A GYM<br>A BETTER YOU</p>
        <span class="h-px max-w-64 grow bg-white/40" aria-hidden="true"></span>
        <span class="ml-auto text-2xl font-black italic tracking-widest text-brand sm:text-4xl" aria-hidden="true">////</span>
    </footer>
</section>
