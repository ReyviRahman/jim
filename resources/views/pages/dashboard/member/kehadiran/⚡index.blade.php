<?php

use App\Models\Attendance;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Layout('layouts::member')] class extends Component
{
    #[Url]
    public string $tab = 'check-in';

    public function with(): array
    {
        $today = today();
        $tomorrow = $today->copy()->addDay();
        $attendances = Attendance::where('user_id', Auth::id())
            ->where(function (Builder $query) use ($today, $tomorrow): void {
                $query->where(function (Builder $query) use ($today, $tomorrow): void {
                    $query->where('check_in_time', '>=', $today)->where('check_in_time', '<', $tomorrow);
                })->orWhere(function (Builder $query) use ($today, $tomorrow): void {
                    $query->where('check_out_time', '>=', $today)->where('check_out_time', '<', $tomorrow);
                });
            })
            ->get();
        $checkIns = $attendances->filter(fn (Attendance $attendance): bool => $attendance->check_in_time?->isSameDay($today) ?? false)->sortByDesc('check_in_time');
        $checkOuts = $attendances->filter(fn (Attendance $attendance): bool => $attendance->check_out_time?->isSameDay($today) ?? false)->sortByDesc('check_out_time');
        $isCheckOut = $this->tab === 'check-out';

        return [
            'checkIns' => $checkIns,
            'checkOuts' => $checkOuts,
            'isCheckOut' => $isCheckOut,
            'records' => $isCheckOut ? $checkOuts : $checkIns,
            'label' => $isCheckOut ? 'Check-out' : 'Check-in',
        ];
    }
};
?>

<main class="attendance-scene" wire:poll.30s>
    <div class="attendance-wall-copy" aria-hidden="true">
        <span>DISCIPLINE<br>BUILDS<br>FREEDOM</span>
        <span>A STRONGER<br>HEALTHIER<br>HAPPIER YOU</span>
    </div>
    <div class="attendance-panel">
    <nav aria-label="Data absensi" class="attendance-tabs grid grid-cols-2">
        @foreach (['check-in' => 'Check-in', 'check-out' => 'Check-out'] as $value => $name)
            <a href="{{ route('member.kehadiran.index', ['tab' => $value]) }}" wire:navigate wire:key="tab-{{ $value }}"
                @if (($value === 'check-out') === $isCheckOut) aria-current="page" @endif
                @class(['flex min-h-11 touch-manipulation items-center justify-center rounded-2xl px-2 py-3 text-sm font-bold focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand sm:text-lg', 'bg-brand text-black' => ($value === 'check-out') === $isCheckOut, 'text-white hover:bg-white/5' => ($value === 'check-out') !== $isCheckOut])>
                {{ $name }}
            </a>
        @endforeach
    </nav>

    <section aria-label="{{ $label }} hari ini" aria-live="polite">
        @if ($records->isNotEmpty())
            <header class="my-7 text-center">
                <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-brand text-secondary">
                    <svg aria-hidden="true" class="h-8 w-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 3v4m8-4v4M4 10h16M6 5h12a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Zm3 10 2 2 4-4" />
                    </svg>
                </div>
                <h1 class="text-2xl font-extrabold tracking-tight sm:text-3xl">{{ $label }} berhasil</h1>
                <p class="mx-auto mt-2 max-w-sm break-words text-sm leading-6 text-neutral-400">
                    @if ($isCheckOut)
                        Sesi latihanmu tercatat. Sampai jumpa lagi, {{ \Illuminate\Support\Str::title(Auth::user()->name) }}!
                    @else
                        Halo, {{ \Illuminate\Support\Str::title(Auth::user()->name) }}! Siap jadi lebih kuat? Selamat latihan di Frans Gym!
                    @endif
                </p>
            </header>
        @endif
        <div class="space-y-4">
            @forelse ($records as $attendance)
                @php
                    $time = $isCheckOut ? $attendance->check_out_time : $attendance->check_in_time;
                @endphp
                <article wire:key="{{ $tab }}-{{ $attendance->id }}" class="overflow-hidden rounded-2xl border border-white/10 bg-neutral-900">
                    <header class="border-b border-white/10 bg-white/5 px-5 py-4 sm:px-6">
                        <time datetime="{{ $time->toDateString() }}" class="text-sm font-semibold leading-6 text-white">{{ $time->locale('id')->translatedFormat('l, j F Y') }}</time>
                    </header>
                    <div class="flex items-center gap-4 px-5 py-6 sm:px-6">
                        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-brand/10 text-brand">
                            <svg aria-hidden="true" class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                @if ($isCheckOut)
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H5v14h4m5-12 5 5-5 5m-5-5h10" />
                                @else
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 5h4v14h-4M9 7l5 5-5 5M4 12h10" />
                                @endif
                            </svg>
                        </div>
                        <div class="min-w-0">
                            <p class="mb-1 text-xs font-medium text-neutral-400">Waktu {{ strtolower($label) }}</p>
                            <time datetime="{{ $time->toIso8601String() }}" class="block">
                                <span class="block text-4xl font-extrabold tracking-tight tabular-nums sm:text-5xl">{{ $time->format('H:i') }}</span>
                            </time>
                        </div>
                    </div>
                    @if ($isCheckOut)
                        @php
                            $durationMinutes = $attendance->check_in_time && $time->greaterThanOrEqualTo($attendance->check_in_time)
                                ? intdiv($time->getTimestamp() - $attendance->check_in_time->getTimestamp(), 60)
                                : null;
                        @endphp
                        <div class="border-t border-white/10 bg-white/5 px-5 py-4 sm:px-6">
                            <p class="text-xs font-medium text-neutral-400">Durasi Sesi</p>
                            <p class="mt-1 text-base font-bold tabular-nums text-brand">
                                @if ($durationMinutes === null)
                                    Belum tersedia
                                @elseif ($durationMinutes < 1)
                                    Kurang dari 1 menit
                                @elseif ($durationMinutes < 60)
                                    {{ $durationMinutes }} menit
                                @else
                                    {{ intdiv($durationMinutes, 60) }} jam {{ $durationMinutes % 60 }} menit
                                @endif
                            </p>
                        </div>
                    @endif
                </article>
            @empty
                <div class="attendance-empty text-center">
                    <div class="attendance-clock mx-auto flex items-center justify-center rounded-full bg-[#262626]">
                        <svg aria-hidden="true" class="h-8 w-8 text-brand sm:h-11 sm:w-11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="12" cy="12" r="8.5" /><path stroke-linecap="round" d="M12 7v5l3 2" /></svg>
                    </div>
                    <h2 class="text-lg font-extrabold sm:text-2xl">Belum ada {{ strtolower($label) }}</h2>
                    <p class="mx-auto mt-2 max-w-md text-pretty text-sm leading-6 text-[#b5b5b5] sm:text-lg sm:leading-8">{{ $isCheckOut ? 'Catatan kepulanganmu akan muncul di sini setelah check-out tercatat.' : 'Catatan kedatanganmu akan muncul di sini setelah kamu melakukan absensi.' }}</p>
                </div>
            @endforelse
        </div>
    </section>
    </div>
    <footer class="attendance-footer" aria-hidden="true">
        <span><i></i>MORE<br>THAN A GYM<br>A BETTER YOU</span>
        <span>NEVER BACKDOWN<br>STAY DEDICATED</span>
    </footer>
</main>
