<?php

use App\Models\Attendance;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;
use Livewire\Component;

new #[Layout('layouts::member')] class extends Component
{
    use WithPagination;

    public function with(): array
    {
        $today = today('Asia/Jakarta');
        $query = Attendance::where('user_id', Auth::id())->whereNotNull('check_in_time');

        return [
            'records' => (clone $query)->where('check_in_time', '>=', $today)
                ->where('check_in_time', '<', $today->copy()->addDay())
                ->orderByDesc('check_in_time')->orderByDesc('id')->get(),
            'history' => $query->with(['membership.gymPackage', 'membership.ptPackage', 'membershipAddon'])
                ->orderByDesc('check_in_time')->orderByDesc('id')
                ->paginate(10, pageName: 'historyPage'),
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
    <h1 class="rounded-2xl bg-brand px-4 py-3 text-center text-lg font-bold text-black">Check-in hari ini</h1>

    <section aria-label="Check-in hari ini" aria-live="polite">
        @if ($records->isNotEmpty())
            <header class="my-7 text-center">
                <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-brand text-secondary">
                    <svg aria-hidden="true" class="h-8 w-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 3v4m8-4v4M4 10h16M6 5h12a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Zm3 10 2 2 4-4" />
                    </svg>
                </div>
                <h2 class="text-2xl font-extrabold tracking-tight sm:text-3xl">Check-in berhasil</h2>
                <p class="mx-auto mt-2 max-w-sm break-words text-sm leading-6 text-neutral-400">
                    Halo, {{ \Illuminate\Support\Str::title(Auth::user()->name) }}! Siap jadi lebih kuat? Selamat latihan di Frans Gym!
                </p>
            </header>
        @endif
        <div class="space-y-4">
            @forelse ($records as $attendance)
                @php
                    $time = $attendance->check_in_time;
                @endphp
                <article wire:key="check-in-{{ $attendance->id }}" class="overflow-hidden rounded-2xl border border-white/10 bg-neutral-900">
                    <header class="border-b border-white/10 bg-white/5 px-5 py-4 sm:px-6">
                        <time datetime="{{ $time->toDateString() }}" class="text-sm font-semibold leading-6 text-white">{{ $time->locale('id')->translatedFormat('l, j F Y') }}</time>
                    </header>
                    <div class="flex items-center gap-4 px-5 py-6 sm:px-6">
                        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-brand/10 text-brand">
                            <svg aria-hidden="true" class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 5h4v14h-4M9 7l5 5-5 5M4 12h10" />
                            </svg>
                        </div>
                        <div class="min-w-0">
                            <p class="mb-1 text-xs font-medium text-neutral-400">Waktu check-in</p>
                            <time datetime="{{ $time->toIso8601String() }}" class="block">
                                <span class="block text-4xl font-extrabold tracking-tight tabular-nums sm:text-5xl">{{ $time->format('H:i') }}</span>
                            </time>
                        </div>
                    </div>
                </article>
            @empty
                <div class="attendance-empty text-center">
                    <div class="attendance-clock mx-auto flex items-center justify-center rounded-full bg-[#262626]">
                        <svg aria-hidden="true" class="h-8 w-8 text-brand sm:h-11 sm:w-11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="12" cy="12" r="8.5" /><path stroke-linecap="round" d="M12 7v5l3 2" /></svg>
                    </div>
                    <h2 class="text-lg font-extrabold sm:text-2xl">Belum ada check-in</h2>
                    <p class="mx-auto mt-2 max-w-md text-pretty text-sm leading-6 text-[#b5b5b5] sm:text-lg sm:leading-8">Catatan kedatanganmu akan muncul di sini setelah kamu melakukan absensi.</p>
                </div>
            @endforelse
        </div>
    </section>
    </div>
    <section id="check-in-history" aria-labelledby="check-in-history-title" class="attendance-panel mt-6">
        <h2 id="check-in-history-title" class="mb-4 text-lg font-bold text-white">Riwayat Check-in</h2>
        <div class="overflow-x-auto rounded-xl border border-white/10">
            <table class="w-full text-left text-sm text-neutral-300">
                <thead class="border-b border-white/10 bg-white/5 text-white">
                    <tr>
                        <th scope="col" class="px-3 py-3">Tanggal</th>
                        <th scope="col" class="px-3 py-3">Jam Check-in</th>
                        <th scope="col" class="px-3 py-3">Jenis</th>
                        <th scope="col" class="px-3 py-3">Paket</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/10">
                    @forelse ($history as $attendance)
                        @php
                            $membership = $attendance->membership;
                            $packageName = $attendance->membership_addon_id !== null
                                ? $attendance->membershipAddon?->name
                                : ($attendance->type === 'pt'
                                    ? ($membership?->pt_package_name_snapshot ?: $membership?->ptPackage?->name ?: $membership?->package_name)
                                    : ($membership?->gym_package_name_snapshot ?: $membership?->gymPackage?->name ?: $membership?->package_name));
                        @endphp
                        <tr wire:key="check-in-history-{{ $attendance->id }}">
                            <td class="whitespace-nowrap px-3 py-3"><time datetime="{{ $attendance->check_in_time->toDateString() }}">{{ $attendance->check_in_time->locale('id')->translatedFormat('d M Y') }}</time></td>
                            <td class="px-3 py-3 tabular-nums"><time datetime="{{ $attendance->check_in_time->toIso8601String() }}">{{ $attendance->check_in_time->format('H:i') }}</time></td>
                            <td class="px-3 py-3">{{ $attendance->type === 'pt' ? 'PT' : 'Gym' }}</td>
                            <td class="min-w-32 break-words px-3 py-3">
                                {{ $packageName ?: '—' }}
                                @if ($attendance->membership_addon_id !== null)
                                    <span class="mt-1 block text-xs font-semibold text-brand">Add-on Gratis</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-3 py-6 text-center">Belum ada riwayat check-in.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $history->links(data: ['scrollTo' => '#check-in-history']) }}</div>
    </section>
    <footer class="attendance-footer" aria-hidden="true">
        <span><i></i>MORE<br>THAN A GYM<br>A BETTER YOU</span>
        <span>NEVER BACKDOWN<br>STAY DEDICATED</span>
    </footer>
</main>
