@props(['day', 'selected' => false, 'coach' => null])

<article {{ $attributes->class(['pt-day-card relative overflow-hidden rounded-2xl border p-4 sm:p-5', 'border-brand bg-[#202014]' => $selected, 'border-white/15 bg-[#121719]' => ! $selected]) }} data-date="{{ $day['date'] }}" aria-label="{{ $day['label'] }}">
    @if($selected)<span class="absolute inset-y-0 left-0 w-1.5 bg-brand" aria-hidden="true"></span>@endif
    <header class="mb-4 flex items-start justify-between gap-3">
        <div>
            <h2 @class(['text-xl font-extrabold sm:text-2xl', 'text-brand' => $selected, 'text-white' => ! $selected])>{{ \Carbon\Carbon::parse($day['date'])->locale('id')->isoFormat('dddd') }}</h2>
            <p class="mt-1 text-sm text-gray-300">{{ \Carbon\Carbon::parse($day['date'])->locale('id')->isoFormat('D MMM YYYY') }}</p>
        </div>
        <span class="shrink-0 rounded-xl bg-white/5 px-3 py-2 text-xs text-gray-300">{{ $day['bookingCount'] }} Booking</span>
    </header>
    @if($day['bookingCount'] === 0)
        <p class="mb-4 flex items-center gap-2 text-xs text-gray-400"><x-member-package-icon name="calendar" class="size-4"/>Belum ada booking Anda</p>
    @endif
    <div class="grid grid-cols-2 gap-2">
        @foreach($day['slots'] as $slot)
            <div wire:key="slot-{{ $day['date'] }}-{{ $slot['time'] }}" class="min-w-0 rounded-xl border border-white/10 bg-black/15 p-3">
                <p class="mb-2 text-xs font-semibold text-white">{{ $slot['label'] }}</p>
                @foreach($slot['ownBookings'] as $booking)
                    <button type="button" wire:key="booking-{{ $day['date'] }}-{{ $slot['time'] }}-{{ $booking['id'] }}" wire:click="openDetailModal({{ $booking['id'] }})" class="mb-2 block w-full rounded-lg border border-white/15 bg-white/5 p-2 text-left text-xs hover:border-brand">
                        <span class="block font-semibold text-white">Booking Saya</span>
                        <span class="mt-1 block break-words text-gray-300">{{ $coach }}</span>
                        <span class="block text-gray-300">{{ $booking['studio'] }}</span>
                        <span @class(['mt-2 inline-block rounded px-1.5 py-1 text-[10px] font-semibold', 'bg-emerald-950 text-emerald-200' => $booking['status'] === 'Approved', 'bg-amber-950 text-amber-200' => in_array($booking['status'], ['Pending', 'Pending Cancel']), 'bg-red-950 text-red-200' => $booking['status'] === 'Rejected', 'bg-slate-800 text-gray-300' => $booking['status'] === 'Cancelled'])>{{ $booking['status'] }}</span>
                    </button>
                @endforeach
                @if($slot['otherBooked'])
                    <p class="text-xs leading-5 text-gray-400">Dibooking member lain</p>
                @elseif(! $slot['occupied'])
                    @if($slot['reason'])
                        <p class="text-[11px] leading-5 text-gray-400">{{ $slot['reason'] }}</p>
                    @else
                        <button type="button" wire:click="openBookingModal('{{ $day['date'] }}', '{{ $slot['time'] }}')" wire:loading.attr="disabled" aria-label="Booking {{ $day['label'] }} {{ $slot['label'] }}" class="flex min-h-11 w-full items-center justify-center gap-1 rounded-lg border border-brand/30 bg-brand/10 px-1 text-xs font-bold text-brand hover:bg-brand hover:text-black disabled:opacity-50">+ Booking</button>
                    @endif
                @endif
            </div>
        @endforeach
    </div>
</article>
