<section class="pt-expired-panel" aria-labelledby="pt-expired-heading">
    <header class="flex items-start gap-4">
        <div class="pt-expired-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="10" cy="7" r="3" />
                <path d="M16 4.5a3 3 0 0 1 0 5.5M3 20v-3a5 5 0 0 1 5-5h4a5 5 0 0 1 5 5v3H3Zm17 0v-3a5 5 0 0 0-3-4.6M7 20v-4h6v4" />
            </svg>
        </div>
        <div class="min-w-0 pt-1">
            <h1 id="pt-expired-heading" class="pt-expired-title">Data PT Expired</h1>
            <p class="pt-expired-description">Pilih coach untuk melihat member dan paket PT yang ditangani.</p>
        </div>
    </header>

    <div class="pt-expired-search">
        <label for="coach-search" class="mb-2 block font-medium">Cari coach</label>
        <div class="relative">
            <svg class="pointer-events-none absolute left-4 top-1/2 size-6 -translate-y-1/2 text-[#bfc1c4]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" aria-hidden="true">
                <circle cx="10.5" cy="10.5" r="7.5" /><path d="m16 16 5 5" />
            </svg>
            <input id="coach-search" type="search" wire:model.live.debounce.300ms="search" placeholder="Cari nama coach..." autocomplete="off" class="pt-expired-search-input">
        </div>
    </div>

    <div class="space-y-2.5">
        @if ($this->unassignedCount > 0)
            <a href="{{ route('admin.pt-expired.unassigned') }}" wire:navigate class="pt-expired-card">
                <span class="pt-expired-avatar pt-expired-avatar-fallback" aria-hidden="true">
                    <svg class="size-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="8" r="4" /><path d="M4 21v-2a8 8 0 0 1 16 0v2" /></svg>
                </span>
                <div class="min-w-0 flex-1">
                    <h2 class="pt-expired-coach-name">Belum ada coach</h2>
                    <p class="pt-expired-coach-role">Perlu penugasan</p>
                </div>
                <span class="pt-expired-count"><strong>{{ $this->unassignedCount }}</strong><span>paket expired</span></span>
                <svg class="pt-expired-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 5 7 7-7 7" /></svg>
            </a>
        @endif

        @forelse ($this->coaches as $coach)
            <a wire:key="coach-{{ $coach->id }}" href="{{ route('admin.pt-expired.coach', ['coach' => $coach->id]) }}" wire:navigate class="pt-expired-card">
                <span class="pt-expired-avatar pt-expired-avatar-fallback relative overflow-hidden" aria-hidden="true">
                    {{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($coach->name, 0, 1)) }}
                    @if ($coach->photo)
                        <img src="{{ asset('storage/'.$coach->photo) }}" alt="" class="absolute inset-0 size-full object-cover object-top" loading="lazy" x-data="{ failed: false }" x-show="!failed" x-init="failed = $el.complete && $el.naturalWidth === 0" x-on:error="failed = true">
                    @endif
                </span>
                <div class="min-w-0 flex-1">
                    <h2 class="pt-expired-coach-name">{{ $coach->name }}</h2>
                    <p class="pt-expired-coach-role">Personal Trainer</p>
                    @if (! $coach->is_active)
                        <span class="mt-1 inline-block rounded border border-white/15 px-1.5 py-0.5 text-xs text-gray-300">Nonaktif</span>
                    @endif
                </div>
                <span class="pt-expired-count"><strong>{{ $coach->active_packages_count }}</strong><span>paket expired</span></span>
                <svg class="pt-expired-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 5 7 7-7 7" /></svg>
            </a>
        @empty
            <p role="status" class="rounded-2xl border border-white/15 p-6 text-center text-[#b8b9bd]">{{ $search !== '' ? 'Tidak ada coach yang cocok dengan pencarian.' : 'Belum ada coach.' }}</p>
        @endforelse
    </div>

    @if ($this->coaches->hasPages())
        <div class="pt-expired-pagination">{{ $this->coaches->links() }}</div>
    @endif

    <footer class="pt-expired-footer">
        <p>Better coaches<br>Stronger members</p>
        <svg width="76" height="32" viewBox="0 0 76 32" fill="none" aria-hidden="true"><path d="M0 32 32 0h18L18 32Z" fill="#79651a" /><path d="M27 32 59 0h17L44 32Z" fill="#c2a52e" /></svg>
    </footer>
</section>
