@props(['memberships', 'search', 'ptOnly', 'installmentFilter' => 'active', 'successMessage' => ''])

<main {{ $attributes->class(['pt-installments']) }} aria-labelledby="pt-installments-title">
    <div class="pt-installments-content">
        <header class="pt-installments-header">
            <div>
                <p class="pt-installments-eyebrow">{{ $ptOnly ? 'Personal Trainer' : 'Membership' }}</p>
                <h1 id="pt-installments-title">{{ $ptOnly ? 'PT' : 'Member' }} <span>Cicilan</span></h1>
                <p class="pt-installments-description">Daftar member yang masih<br>memiliki sisa tagihan {{ $ptOnly ? 'PT' : 'membership' }}.</p>
            </div>
            <p class="pt-installments-signature" aria-hidden="true">Never Back Down<br><span>Stay Dedicated</span></p>
        </header>

        @if ($successMessage)
            <p role="status" class="mb-4 rounded-md bg-green-50 p-3 text-green-800">{{ $successMessage }}</p>
        @endif
        @error('installment')<p role="alert" class="mb-4 rounded-md bg-red-50 p-3 text-red-700">{{ $message }}</p>@enderror
        @if ($ptOnly)
            <div class="mb-4 flex items-center gap-3">
                <label for="pt-installment-filter">Status cicilan</label>
                <select id="pt-installment-filter" wire:model.live="installmentFilter" class="rounded-md border border-gray-300 bg-white p-2 text-gray-900">
                    <option value="active">Aktif</option>
                    <option value="expired">Hangus</option>
                </select>
            </div>
        @endif

        <div class="pt-installments-search">
            <label for="pt-installments-search" class="sr-only">Cari nama member</label>
            <x-installment-icon name="search" />
            <input id="pt-installments-search" type="search" wire:model.live.debounce.500ms="search" placeholder="Cari nama member..." autocomplete="off">
        </div>

        <div class="pt-installments-list" wire:loading.class="opacity-60" wire:target="search,installmentFilter,markExpired,restoreInstallment">
            @forelse ($memberships as $membership)
                @php
                    $memberName = $membership->members->isNotEmpty()
                        ? $membership->members->pluck('name')->implode(', ')
                        : ($membership->user?->name ?? 'User Dihapus');
                    $initialName = $membership->members->first()?->name ?? $membership->user?->name ?? 'User Dihapus';
                    $initials = \Illuminate\Support\Str::of($initialName)->squish()->explode(' ')->take(2)
                        ->map(fn (string $word) => \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($word, 0, 1)))->implode('');
                    $packageName = $membership->type === 'visit'
                        ? 'Visit Harian'
                        : ($membership->gymPackage?->name ?? $membership->ptPackage?->name ?? $membership->package_name ?? 'Paket Custom');
                    $packageLines = preg_split('/\s+(?=\d+\s+Sessions?$)/i', $packageName, 2);
                @endphp
                <article class="pt-installment-card" wire:key="pt-installment-{{ $membership->id }}">
                    <header class="pt-installment-card-header">
                        <span class="pt-installment-avatar" aria-hidden="true">{{ $initials }}</span>
                        <a class="pt-installment-member" href="{{ route('admin.cicilan.pay', $membership) }}" wire:navigate>
                            <h2>{{ $memberName }}</h2>
                            <x-installment-icon name="chevron" />
                        </a>
                        <details class="pt-installment-menu">
                            <summary aria-label="Opsi tagihan {{ $memberName }}">•••</summary>
                            <a href="{{ route('admin.riwayat.membership.invoice', $membership) }}">Unduh invoice</a>
                        </details>
                    </header>

                    <dl class="pt-installment-details">
                        <div class="pt-installment-row">
                            <dt><x-installment-icon name="user" /><span>Admin</span></dt>
                            <dd>{{ $membership->admin?->name ?? '-' }}</dd>
                        </div>
                        <div class="pt-installment-row">
                            <dt><x-installment-icon name="user" /><span>Follow Up</span></dt>
                            <dd>{{ $membership->followUp?->name ?? '-' }}</dd>
                        </div>
                        <div class="pt-installment-row pt-installment-package">
                            <dt><x-installment-icon name="package" /><span>Paket Layanan</span></dt>
                            <dd>@foreach ($packageLines as $line)<span class="block">{{ $line }}</span>@endforeach</dd>
                        </div>
                        <div class="pt-installment-row">
                            <dt><x-installment-icon name="money" /><span>Total Tagihan</span></dt>
                            <dd class="pt-installment-amount">Rp {{ number_format($membership->price_paid, 0, ',', '.') }}</dd>
                        </div>
                        <div class="pt-installment-row">
                            <dt><x-installment-icon name="card" /><span>Sudah Dibayar</span></dt>
                            <dd class="pt-installment-amount pt-installment-paid">Rp {{ number_format($membership->total_paid, 0, ',', '.') }}</dd>
                        </div>
                        <div class="pt-installment-row">
                            <dt><x-installment-icon name="clock" /><span>Sisa Tagihan</span></dt>
                            <dd class="pt-installment-amount pt-installment-due">Rp {{ number_format($membership->price_paid - $membership->total_paid, 0, ',', '.') }}</dd>
                        </div>
                    </dl>
                    @if ($ptOnly)
                        <div class="flex flex-wrap items-center justify-between gap-3 p-4">
                            @if ($membership->pt_installment_expired_at)
                                <span class="rounded-md bg-amber-100 px-3 py-1 text-sm font-semibold text-amber-900">Hangus</span>
                                <button type="button" wire:click="restoreInstallment({{ $membership->id }})" wire:confirm="Pulihkan cicilan PT ke daftar Aktif?" wire:loading.attr="disabled" class="rounded-md border border-gray-400 bg-white px-4 py-2 text-sm text-gray-900 disabled:opacity-50">Pulihkan</button>
                            @else
                                <button type="button" wire:click="markExpired({{ $membership->id }})" wire:confirm="Tandai cicilan PT sebagai Hangus? Data akan disembunyikan dari daftar Aktif. Tagihan dan sesi PT tidak berubah." wire:loading.attr="disabled" class="rounded-md border border-red-600 bg-white px-4 py-2 text-sm text-red-700 disabled:opacity-50">Tandai Hangus</button>
                            @endif
                        </div>
                    @endif
                    <a href="{{ route('admin.cicilan.pay', $membership) }}" wire:navigate class="pt-installment-pay" aria-label="Bayar cicilan {{ $memberName }}">
                        <span><x-installment-icon name="card" />Bayar Cicilan</span>
                        <x-installment-icon name="chevron" />
                    </a>
                </article>
            @empty
                <p role="status" class="pt-installments-empty">{{ $search !== '' ? 'Tidak ada member yang cocok dengan pencarian.' : ($ptOnly ? ($installmentFilter === 'expired' ? 'Tidak ada cicilan PT Hangus.' : 'Tidak ada cicilan PT aktif.') : 'Tidak ada tagihan membership yang tertunda. Semua lunas!') }}</p>
            @endforelse
        </div>
        @if ($memberships->hasPages())
            <div class="pt-installments-pagination">{{ $memberships->links() }}</div>
        @endif
    </div>
</main>
