<?php

namespace App\Livewire\Pages\Dashboard\Admin\SesiPt;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

new #[Layout('layouts::admin')] class extends Component
{
    public string $search = '';

    public string $dateStart = '';

    public string $dateEnd = '';

    #[Locked]
    public string $periodStart = '';

    #[Locked]
    public string $periodEnd = '';

    public function mount(): void
    {
        $this->resetPeriod();
    }

    public function resetPeriod(): void
    {
        $month = CarbonImmutable::today(config('app.timezone'))->startOfMonth();
        $this->dateStart = $month->subMonth()->day(16)->toDateString();
        $this->dateEnd = $month->day(15)->toDateString();
        $this->applyPeriod();
    }

    public function applyPeriod(): void
    {
        $this->validate([
            'dateStart' => ['required', 'date_format:Y-m-d'],
            'dateEnd' => ['required', 'date_format:Y-m-d', 'after_or_equal:dateStart'],
        ], [
            'dateStart.required' => 'Pilih tanggal mulai.',
            'dateStart.date_format' => 'Tanggal mulai tidak valid.',
            'dateEnd.required' => 'Pilih tanggal akhir.',
            'dateEnd.date_format' => 'Tanggal akhir tidak valid.',
            'dateEnd.after_or_equal' => 'Tanggal akhir harus sama atau setelah tanggal mulai.',
        ]);

        $this->periodStart = $this->dateStart;
        $this->periodEnd = $this->dateEnd;
        unset($this->ptUsers);
    }

    #[Computed]
    public function ptUsers(): Collection
    {
        $inPeriod = fn (Builder $query) => $query
            ->whereNotNull('pt_package_id')
            ->whereIn('status', ['active', 'completed'])
            ->whereDate('start_date', '<=', $this->periodEnd)
            ->whereDate('pt_end_date', '>=', $this->periodStart);

        return User::where('role', 'pt')
            ->where('is_active', true)
            ->when($this->search, fn (Builder $query) => $query->where('name', 'like', '%'.$this->search.'%'))
            ->with(['ptMemberships' => fn (HasMany $query) => $query->where($inPeriod)->with('members:id')])
            ->withCount(['ptBookingsAsPt as total_sessions' => fn (Builder $query) => $query
                ->where('status', 'approved')
                ->where('attendance', 'attended')
                ->where('is_free', false)
                ->whereBetween('booking_date', [$this->periodStart, $this->periodEnd])])
            ->orderBy('name')
            ->get();
    }
};
?>

<main class="coach-directory" aria-labelledby="coach-heading">
    <div class="coach-directory-content">
        <header class="coach-directory-heading">
            <p class="coach-directory-eyebrow">Coach Management</p>
            <h1 id="coach-heading">Data Personal <span>Trainer</span></h1>
            <p>Kelola data coach FRANSGYM untuk performa tim<br class="hidden sm:block"> yang lebih maksimal.</p>
        </header>
        @foreach (['success', 'error'] as $messageType)
            @if (session()->has($messageType))
                <p role="status" class="my-4 rounded-xl border border-white/20 bg-black/80 p-4 text-white">{{ session($messageType) }}</p>
            @endif
        @endforeach
        <div class="coach-directory-toolbar">
            <div class="coach-directory-search">
                <label for="coach-search" class="sr-only">Cari nama PT</label>
                <x-coach-icon name="search" />
                <input id="coach-search" type="search" wire:model.live.debounce.300ms="search" placeholder="Cari nama PT..." autocomplete="off">
            </div>
            @if (auth()->user()->role === 'admin' || auth()->user()->isHeadCoach())
                <a href="{{ route('admin.akun.trainer.create') }}" wire:navigate class="coach-directory-add" aria-label="Tambah coach"><x-coach-icon name="add" /> Coach</a>
            @endif
        </div>
        <form wire:submit="applyPeriod" class="coach-directory-period">
            <div class="coach-directory-period-fields">
                <div>
                    <label for="coach-date-start">Tanggal mulai</label>
                    <input id="coach-date-start" type="date" wire:model="dateStart" required>
                    @error('dateStart') <p role="alert" class="coach-directory-period-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="coach-date-end">Tanggal akhir</label>
                    <input id="coach-date-end" type="date" wire:model="dateEnd" required>
                    @error('dateEnd') <p role="alert" class="coach-directory-period-error">{{ $message }}</p> @enderror
                </div>
                <button type="submit" class="coach-directory-add" wire:loading.attr="disabled" wire:target="applyPeriod,resetPeriod">Terapkan</button>
                <button type="button" class="coach-directory-period-reset" wire:click="resetPeriod" wire:loading.attr="disabled" wire:target="applyPeriod,resetPeriod">Periode bulan ini</button>
            </div>
            <p aria-live="polite">Periode: <strong>{{ \Carbon\CarbonImmutable::parse($periodStart)->locale('id')->translatedFormat('d F Y') }} – {{ \Carbon\CarbonImmutable::parse($periodEnd)->locale('id')->translatedFormat('d F Y') }}</strong></p>
            <p>Member Aktif: member unik dengan masa aktif paket PT yang bersinggungan dengan periode ini. Total Sesi: booking Hadir dalam periode ini, tidak termasuk sesi gratis.</p>
        </form>
        <div class="coach-directory-list" wire:loading.class="opacity-60" wire:target="search,applyPeriod,resetPeriod">
            @forelse ($this->ptUsers as $pt)
                <article class="coach-directory-card" wire:key="pt-{{ $pt->id }}">
                    <div class="coach-directory-photo">
                        <span class="coach-directory-initial" aria-hidden="true">{{ \Illuminate\Support\Str::of($pt->name)->replaceStart('Coach ', '')->substr(0, 1)->upper() }}</span>
                        @if ($pt->photo)
                            <img src="{{ asset('storage/'.$pt->photo) }}" alt="{{ $pt->name }}" loading="lazy" x-data="{ failed: false }" x-show="!failed" x-init="failed = $el.complete && $el.naturalWidth === 0" x-on:error="failed = true">
                        @endif
                        <span class="coach-directory-number">#{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                    </div>
                    <div class="coach-directory-info">
                        <div class="coach-directory-card-heading">
                            <div class="min-w-0">
                                <h2>{{ $pt->name }} <svg class="coach-directory-badge" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="m12 1 3 2 4 .5.5 4L22 11l-2 4-.5 4-4 .5-3.5 3-3.5-3-4-.5-.5-4L1 11l2.5-3.5.5-4L8 3Z"/><path d="m7 12 3 3 6-7" fill="none" stroke="#151515" stroke-width="2"/></svg></h2>
                                <span class="coach-directory-role">{{ $pt->isHeadCoach() ? 'Head Coach' : 'Personal Trainer' }}</span>
                            </div>
                            <span @class(['coach-directory-status', 'is-active' => $pt->is_active])><span></span>{{ $pt->is_active ? 'Aktif' : 'Nonaktif' }}</span>
                            @if (auth()->user()->role === 'admin' || auth()->user()->isHeadCoach())
                                <details class="coach-directory-menu">
                                    <summary aria-label="Opsi {{ $pt->name }}">•••</summary>
                                    <a href="{{ route('admin.akun.trainer.edit', $pt) }}" wire:navigate>Edit coach</a>
                                </details>
                            @endif
                        </div>
                        <div class="coach-directory-contact"><x-coach-icon name="mail" /><span>{{ $pt->email ?: 'Email belum diisi' }}</span></div>
                        <div class="coach-directory-contact"><x-coach-icon name="phone" /><span>{{ $pt->phone ?: 'Telepon belum diisi' }}</span></div>
                        <div class="coach-directory-card-footer">
                            <div class="coach-directory-stat"><x-coach-icon name="users" /><div><strong>{{ $pt->ptMemberships->flatMap->members->unique('id')->count() }}</strong><span>Member Aktif</span></div></div>
                            <div class="coach-directory-stat"><x-coach-icon name="calendar" /><div><strong>{{ (int) $pt->total_sessions }}</strong><span>Total Sesi</span></div></div>
                            <a href="{{ route('admin.sesi-pt.detail', $pt) }}" wire:navigate class="coach-directory-detail" aria-label="Detail {{ $pt->name }}"><x-coach-icon name="eye" />Detail</a>
                        </div>
                    </div>
                </article>
            @empty
                <p role="status" class="coach-directory-empty">{{ $search !== '' ? 'Tidak ada coach yang cocok dengan pencarian.' : 'Belum ada data personal trainer.' }}</p>
            @endforelse
        </div>
    </div>
</main>
