@props(['packages', 'title', 'headingId'])

<section class="px-3 sm:px-7" aria-labelledby="{{ $headingId }}">
    <h2 id="{{ $headingId }}" class="mb-4 text-lg font-bold text-white sm:text-xl">{{ $title }}</h2>
    <div class="space-y-3 sm:space-y-4">
        @forelse ($packages as $package)
            <article wire:key="{{ $headingId }}-{{ $package['id'] }}" @class(['relative overflow-hidden rounded-2xl border bg-neutral-950 p-4 sm:p-6', 'border-brand/60' => $package['saving'] !== null, 'border-white/15' => $package['saving'] === null])>
                @if ($package['saving'] !== null)
                    <span class="absolute right-0 top-0 rounded-bl-xl bg-brand px-3 py-1 text-xs font-bold text-black sm:px-4" aria-label="Hemat {{ $package['saving'] }} persen">-{{ $package['saving'] }}%</span>
                @endif
                <h3 class="mb-4 pr-16 text-xs font-medium text-gray-300 sm:text-sm">{{ $package['name'] }}</h3>
                <div class="grid grid-cols-[3.5rem_minmax(0,1fr)_minmax(0,1fr)] items-center gap-3 sm:grid-cols-[6rem_minmax(0,1fr)_minmax(0,1fr)] sm:gap-6">
                    <div class="border-r border-brand/40 pr-3 text-center sm:pr-6">
                        <p class="text-3xl font-bold leading-none text-white sm:text-4xl">{{ $package['quantity'] ?? '—' }}</p>
                        <p class="mt-2 text-xs text-gray-400 sm:text-sm">{{ $package['unit'] }}</p>
                    </div>
                    <div class="min-w-0">
                        <p class="break-words text-sm font-bold tabular-nums text-brand sm:text-2xl">{{ $package['unit_price'] }}</p>
                        <p class="mt-1 text-xs text-gray-400 sm:text-sm">{{ $package['period'] }}</p>
                    </div>
                    <div class="min-w-0 text-right">
                        @if ($package['original'] !== null)
                            <p class="break-words text-xs tabular-nums text-gray-500 sm:text-sm"><span class="sr-only">Harga normal </span><s>{{ $package['original'] }}</s></p>
                        @endif
                        <p class="mt-1 break-words text-sm font-semibold tabular-nums text-white sm:text-xl">{{ $package['total'] }}</p>
                        <p class="mt-1 text-xs text-gray-400">Total paket</p>
                    </div>
                </div>
                @if ($package['max_members'] > 1)
                    <p class="mt-4 text-xs text-gray-400">Untuk {{ $package['max_members'] }} orang · Harga untuk seluruh anggota paket</p>
                @endif
            </article>
        @empty
            <p class="rounded-2xl border border-white/10 bg-neutral-900 p-5 text-sm text-gray-400">Belum ada paket yang tersedia saat ini.</p>
        @endforelse
    </div>
</section>
