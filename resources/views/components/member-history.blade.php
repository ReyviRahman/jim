@props(['user', 'memberships'])

<main class="member-detail" aria-labelledby="member-detail-title">
    <header class="member-detail-header">
        <a href="{{ route('admin.riwayat.index') }}" wire:navigate class="member-detail-back" aria-label="Kembali ke Riwayat"><x-member-detail-icon name="back" /></a>
        <h1 id="member-detail-title">Detail Member</h1>
        <a href="{{ route('admin.akun.member.edit', $user) }}" wire:navigate class="member-detail-button"><x-member-detail-icon name="edit" />Edit Member</a>
    </header>
    @foreach (['success', 'error'] as $messageType)
        @if (session()->has($messageType))
            <p class="member-detail-notice" role="alert">{{ session($messageType) }}</p>
        @endif
    @endforeach

    <section class="member-detail-profile member-detail-panel" aria-label="Profil member">
        <div class="member-detail-photo">
            <span aria-hidden="true">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($user->name, 0, 1)) }}</span>
            @if ($user->photo)
                <img src="{{ asset('storage/'.$user->photo) }}" alt="{{ $user->name }}" x-data="{ failed: false }" x-show="!failed" x-init="failed = $el.complete && $el.naturalWidth === 0" x-on:error="failed = true">
            @endif
        </div>
        <div class="member-detail-identity">
            <h2>{{ $user->name }}</h2>
            <p>{{ $user->email }}</p>
            <span @class(['member-detail-status', 'is-active' => $user->is_active])><i></i>{{ $user->is_active ? 'Aktif' : 'Tidak Aktif' }}</span>
        </div>
        <div class="member-detail-number"><span class="member-detail-number-icon"><x-member-detail-icon name="user" /></span><div><p>No. Member</p><strong>{{ $user->id }}</strong></div></div>
    </section>

    @forelse ($memberships as $membership)
        @php
            $originalPrice = $membership->price_paid + $membership->discount_applied;
            $discountPercent = $originalPrice > 0 ? round($membership->discount_applied / $originalPrice * 100, 1) : 0;
            $packageName = $membership->gymPackage?->name ?? $membership->package_name ?? '';
            $months = null;
            if ($membership->type === 'membership') {
                if (preg_match('/\b(\d+)\s+(?:bulan\s+)?(?:plus|free)\s+(\d+)\s+bulan\b/i', $packageName, $duration)) {
                    $months = (int) $duration[1] + (int) $duration[2];
                } elseif (preg_match('/\b(\d+)\s+(?:monthly\s+pass|bulan)\b/i', $packageName, $duration)) {
                    $months = (int) $duration[1];
                } elseif (preg_match('/\byearly\s+pass\b/i', $packageName)) {
                    $months = 12;
                }
            }
            $statusLabel = match ($membership->status) {
                'active' => 'Aktif', 'pending' => 'Menunggu', 'expired' => 'Kadaluarsa',
                'cancelled' => 'Dibatalkan', default => ucfirst($membership->status),
            };
        @endphp
        <section class="member-detail-membership" wire:key="membership-{{ $membership->id }}" aria-label="Paket {{ $loop->iteration }}">
            @if ($memberships->count() > 1 || $membership->status !== 'active')
                <div class="member-detail-package-heading"><h3>Riwayat Paket #{{ $membership->id }}</h3><span>{{ $statusLabel }}</span></div>
            @endif
            <div class="member-detail-summary">
                <div class="member-detail-panel member-detail-feature">
                    <span class="member-detail-icon"><x-member-detail-icon name="gym" /></span>
                    <div><p class="member-detail-label">Program / Paket</p>
                        @if (in_array($membership->type, ['membership', 'bundle_pt_membership', 'visit']))
                            <h3>PAKET {{ $membership->type === 'visit' ? 'HARIAN' : 'GYM' }}</h3>
                            <p class="member-detail-package-name">{{ $membership->gymPackage?->name ?? $membership->package_name ?? 'Paket Terhapus' }}</p>
                        @endif
                        @if (in_array($membership->type, ['pt', 'bundle_pt_membership']))
                            <h3>PAKET TRAINER</h3><p class="member-detail-package-name">{{ $membership->ptPackage?->name ?? $membership->package_name ?? 'Paket Terhapus' }}</p>
                            <p class="member-detail-label">Coach: {{ $membership->personalTrainer?->name ?? '-' }}</p>
                            @if ($membership->total_sessions)<p class="member-detail-label">Sisa Sesi: {{ $membership->remaining_sessions }} / {{ $membership->total_sessions }}</p>@endif
                        @endif
                    </div>
                </div>
                <div class="member-detail-panel member-detail-feature">
                    <span class="member-detail-icon"><x-member-detail-icon name="wallet" /></span>
                    <div><p class="member-detail-label">{{ $membership->isOperational() ? 'Ditanggung Operasional' : 'Total Bayar' }}</p>
                        @if ($membership->discount_applied > 0)
                            <div class="member-detail-discount"><del>Rp {{ number_format($originalPrice, 0, ',', '.') }}</del><span>-{{ $discountPercent }}%</span></div>
                            <p class="member-detail-discount-amount">Diskon Rp {{ number_format($membership->discount_applied, 0, ',', '.') }}</p>
                        @endif
                        <strong class="member-detail-total">Rp {{ number_format($membership->price_paid, 0, ',', '.') }}</strong>
                    </div>
                </div>
            </div>
            @if ($months > 0)
                <div class="member-detail-panel member-detail-monthly">
                    <span class="member-detail-icon"><x-member-detail-icon name="chart" /></span>
                    <div><h3>Harga per Bulan</h3><p class="member-detail-label">Dari total pembelian Rp {{ number_format($membership->price_paid, 0, ',', '.') }} ({{ $months }} bulan)</p></div>
                    <div class="member-detail-monthly-price"><x-member-detail-icon name="calendar" /><div><strong>Rp {{ number_format($membership->price_paid / $months, 0, ',', '.') }}</strong><p>per bulan</p></div></div>
                </div>
            @endif
            <div class="member-detail-panel member-detail-dates">
                <div class="member-detail-row-label"><span class="member-detail-icon"><x-member-detail-icon name="calendar" /></span><span>Masa Aktif</span></div>
                <div class="member-detail-date"><x-member-detail-icon name="play" /><div><p>Mulai</p><strong>{{ $membership->start_date?->format('d M Y') ?? 'BELUM AKTIF' }}</strong></div></div>
                @if (in_array($membership->type, ['membership', 'bundle_pt_membership']))
                    <div class="member-detail-date"><x-member-detail-icon name="check" /><div><p>Gym s/d</p><strong>{{ $membership->membership_end_date?->format('d M Y') ?? 'BELUM AKTIF' }}</strong></div></div>
                @endif
                @if (in_array($membership->type, ['pt', 'bundle_pt_membership']))
                    <div class="member-detail-date"><x-member-detail-icon name="check" /><div><p>PT s/d</p><strong>{{ $membership->pt_end_date?->format('d M Y') ?? 'BELUM AKTIF' }}</strong></div></div>
                @endif
                @if ($membership->type === 'visit')<strong>Berlaku 1 Hari</strong>@endif
            </div>
            <div class="member-detail-panel member-detail-followups">
                <div><span class="member-detail-icon"><x-member-detail-icon name="user" /></span><div><p>Admin Follow Up</p><strong>{{ $membership->followUp?->name ?? '-' }}</strong></div></div>
                <div><span class="member-detail-icon"><x-member-detail-icon name="users" /></span><div><p>Sales Follow Up</p><strong>{{ $membership->followUpTwo?->name ?? '-' }}</strong></div></div>
            </div>
            <div class="member-detail-panel member-detail-invoice">
                <span class="member-detail-icon"><x-member-detail-icon name="invoice" /></span><span>Invoice</span>
                <a href="{{ route('admin.riwayat.membership.invoice', $membership) }}" class="member-detail-button"><x-member-detail-icon name="invoice" />Lihat Invoice</a>
            </div>
            <details class="member-detail-extra">
                <summary>Informasi paket lainnya{{ auth()->user()->role === 'admin' ? ' & kelola' : '' }}</summary>
                <p>Member: {{ $membership->members->isNotEmpty() ? $membership->members->pluck('name')->implode(', ') : ($membership->user?->name ?? 'N/A') }}</p>
                <p>Status paket: {{ $statusLabel }}</p>
                @if (auth()->user()->role === 'admin')
                    @php($priceLabelData = $membership->getPriceLabel())
                    @if ($priceLabelData)<p>{{ $priceLabelData['label'] }}</p>@endif
                    <div class="member-detail-actions">
                        @if ($membership->type === 'pt')
                            <a href="{{ route('admin.membership.hold', ['membership' => $membership, 'user' => $user->id]) }}" wire:navigate>Hold</a>
                        @endif
                        <a href="{{ route('admin.membership.edit', $membership) }}" wire:navigate>Edit Paket</a>
                        <button type="button" wire:click="delete({{ $membership->id }})" wire:confirm="Apakah Anda yakin ingin menghapus membership ini?" wire:loading.attr="disabled">Hapus Membership</button>
                    </div>
                @endif
            </details>
        </section>
    @empty
        <p class="member-detail-panel member-detail-empty">Belum ada riwayat membership untuk user ini.</p>
    @endforelse
    <details class="member-detail-extra"><summary>Informasi personal</summary><p>No HP / WhatsApp: {{ $user->phone ?? '-' }}</p><p>Role: {{ str_replace('_', ' ', $user->role) }}</p></details>
</main>
