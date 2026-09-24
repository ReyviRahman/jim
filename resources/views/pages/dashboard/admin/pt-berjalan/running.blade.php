<section class="pt-running-panel" aria-labelledby="pt-running-heading">
    <img src="{{ asset('member-pt-studio-wide.png') }}" alt="" class="pt-running-hero" aria-hidden="true">
    <header class="pt-running-header">
        <p class="pt-running-eyebrow">Personal Trainer</p>
        <h1 id="pt-running-heading">Data PT Berjalan</h1>
        <p class="pt-running-description">Pilih coach untuk melihat member dan paket PT yang ditangani.</p>
    </header>

    <div class="pt-running-search">
        <label for="coach-search">Cari coach</label>
        <div class="relative">
            <svg class="pointer-events-none absolute left-5 top-1/2 size-6 -translate-y-1/2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><circle cx="10.5" cy="10.5" r="7.5" /><path d="m16 16 5 5" /></svg>
            <input id="coach-search" type="search" wire:model.live.debounce.300ms="search" placeholder="Cari nama coach..." autocomplete="off">
        </div>
    </div>

    <div class="pt-running-list">
        @if ($this->unassignedCount > 0)
            <a href="{{ route('admin.pt-berjalan.unassigned') }}" wire:navigate class="pt-running-card">
                <span class="pt-running-avatar" aria-hidden="true"><svg class="size-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="8" r="4" /><path d="M4 21v-2a8 8 0 0 1 16 0v2" /></svg></span>
                <div class="min-w-0 flex-1">
                    <h2>Belum ada coach</h2>
                    <p class="pt-running-role">Perlu penugasan</p>
                </div>
                <span class="pt-running-count"><strong>{{ $this->unassignedCount }}</strong><span>member aktif</span></span>
                <svg class="pt-running-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 5 7 7-7 7" /></svg>
            </a>
        @endif

        @forelse ($this->coaches as $coach)
            <a wire:key="coach-{{ $coach->id }}" href="{{ route('admin.pt-berjalan.coach', ['coach' => $coach->id]) }}" wire:navigate class="pt-running-card">
                <span class="pt-running-avatar relative overflow-hidden" aria-hidden="true">
                    {{ \Illuminate\Support\Str::of($coach->name)->replaceStart('Head Coach ', '')->replaceStart('Coach ', '')->substr(0, 1)->upper() }}
                    @if ($coach->photo)
                        <img src="{{ asset('storage/'.$coach->photo) }}" alt="" class="absolute inset-0 size-full object-cover object-top" loading="lazy" x-data="{ failed: false }" x-show="!failed" x-init="failed = $el.complete && $el.naturalWidth === 0" x-on:error="failed = true">
                    @endif
                </span>
                <div class="min-w-0 flex-1">
                    <h2>{{ $coach->name }}</h2>
                    <p class="pt-running-role">{{ $coach->isHeadCoach() ? 'Head Coach' : 'Personal Trainer' }}</p>
                    @if (! $coach->is_active)
                        <span class="mt-1 inline-block rounded border border-white/15 px-1.5 py-0.5 text-xs text-gray-300">Nonaktif</span>
                    @endif
                </div>
                <span class="pt-running-count">
                    <span class="flex items-center gap-2">
                        <svg class="pt-running-members-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="9" cy="7" r="3" /><path d="M16 4a3 3 0 0 1 0 6M2 20v-2a6 6 0 0 1 12 0v2H2Zm15 0h5v-2a6 6 0 0 0-5-5" /></svg>
                        <strong>{{ $coach->active_packages_count }}</strong>
                    </span>
                    <span>member aktif</span>
                </span>
                <svg class="pt-running-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 5 7 7-7 7" /></svg>
            </a>
        @empty
            <p role="status" class="rounded-2xl border border-white/15 p-6 text-center text-[#b8b9bd]">{{ $search !== '' ? 'Tidak ada coach yang cocok dengan pencarian.' : 'Belum ada coach.' }}</p>
        @endforelse
    </div>

    @if ($this->coaches->hasPages())
        <div class="pt-running-pagination">{{ $this->coaches->links() }}</div>
    @endif

    <footer class="pt-running-footer">
        <div class="pt-running-motto"><p>More<br>than a gym</p><span>A stronger<br>you everyday</span></div>
        <p class="pt-running-signature">Never Back Down<br><span>Stay Dedicated</span></p>
    </footer>
</section>
