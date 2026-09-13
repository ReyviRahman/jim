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

<main class="mx-auto max-w-2xl py-2 text-secondary sm:px-4 sm:py-6" wire:poll.30s>
    <nav aria-label="Data absensi" class="mb-7 grid grid-cols-2 gap-1 rounded-2xl bg-gray-100 p-1.5">
        @foreach (['check-in' => 'Check-in', 'check-out' => 'Check-out'] as $value => $name)
            <a href="{{ route('member.kehadiran.index', ['tab' => $value]) }}" wire:navigate wire:key="tab-{{ $value }}"
                @if (($value === 'check-out') === $isCheckOut) aria-current="page" @endif
                @class(['flex min-h-12 touch-manipulation items-center justify-center gap-2 rounded-xl px-2 py-3 text-sm font-bold focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-secondary', 'bg-secondary text-white shadow-sm' => ($value === 'check-out') === $isCheckOut, 'text-gray-600 hover:bg-white' => ($value === 'check-out') !== $isCheckOut])>
                {{ $name }}
            </a>
        @endforeach
    </nav>

    <section aria-label="{{ $label }} hari ini" aria-live="polite">
        @if ($records->isNotEmpty())
            <header class="mb-7 text-center">
                <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-brand text-secondary">
                    <svg aria-hidden="true" class="h-8 w-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 3v4m8-4v4M4 10h16M6 5h12a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Zm3 10 2 2 4-4" />
                    </svg>
                </div>
                <h1 class="text-2xl font-extrabold tracking-tight sm:text-3xl">{{ $label }} berhasil</h1>
                <p class="mx-auto mt-2 max-w-sm break-words text-sm leading-6 text-gray-600">
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
                <article wire:key="{{ $tab }}-{{ $attendance->id }}" class="overflow-hidden rounded-2xl border border-gray-200 bg-white">
                    <header class="border-b border-gray-200 bg-gray-50 px-5 py-4 sm:px-6">
                        <time datetime="{{ $time->toDateString() }}" class="text-sm font-semibold leading-6 text-secondary">{{ $time->locale('id')->translatedFormat('l, j F Y') }}</time>
                    </header>
                    <div class="flex items-center gap-4 px-5 py-6 sm:px-6">
                        <div @class(['flex h-12 w-12 shrink-0 items-center justify-center rounded-full', 'bg-brand text-secondary' => !$isCheckOut, 'bg-secondary text-white' => $isCheckOut])>
                            <svg aria-hidden="true" class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                @if ($isCheckOut)
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H5v14h4m5-12 5 5-5 5m-5-5h10" />
                                @else
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 5h4v14h-4M9 7l5 5-5 5M4 12h10" />
                                @endif
                            </svg>
                        </div>
                        <div class="min-w-0">
                            <p class="mb-1 text-xs font-medium text-gray-500">Waktu {{ strtolower($label) }}</p>
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
                        <div class="border-t border-gray-200 bg-gray-50 px-5 py-4 sm:px-6">
                            <p class="text-xs font-medium text-gray-500">Durasi Sesi</p>
                            <p class="mt-1 text-base font-bold tabular-nums text-secondary">
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
                <div class="rounded-2xl border border-gray-200 bg-gray-50 px-5 py-10 text-center sm:py-14">
                    <div class="mx-auto mb-5 flex h-16 w-16 items-center justify-center rounded-full bg-white ring-1 ring-gray-200">
                        <svg aria-hidden="true" class="h-7 w-7 text-gray-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="8.5" /><path stroke-linecap="round" d="M12 7v5l3 2" /></svg>
                    </div>
                    <h2 class="text-lg font-bold">Belum ada {{ strtolower($label) }}</h2>
                    <p class="mx-auto mt-2 max-w-xs text-pretty text-sm leading-6 text-gray-600">{{ $isCheckOut ? 'Catatan kepulanganmu akan muncul di sini setelah check-out tercatat.' : 'Catatan kedatanganmu akan muncul di sini setelah kamu melakukan absensi.' }}</p>
                    @unless ($isCheckOut)
                        <a href="{{ route('member.absensi') }}" wire:navigate class="mt-6 inline-flex min-h-12 touch-manipulation items-center justify-center rounded-xl bg-brand px-6 py-3 text-sm font-bold text-secondary hover:bg-yellow-300 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-secondary">Buka absensi</a>
                    @endunless
                </div>
            @endforelse
        </div>
    </section>
</main>
