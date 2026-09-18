@props(['members', 'records' => [], 'required' => false])
@php($terms = collect($records)->first()['terms_snapshot'] ?? \App\MembershipWaiverTerms::snapshot())

<section x-data x-on:membership-waiver-invalid.window="$nextTick(() => {
        const field = document.getElementById($event.detail.field) || $el;
        field.focus({ preventScroll: true });
        field.scrollIntoView({ behavior: 'smooth', block: 'center' });
    })" tabindex="-1" class="space-y-5 rounded-md border border-default bg-neutral-primary-soft p-4 shadow-xs sm:p-6" aria-label="Persetujuan &amp; Waiver">
    <div>
        <h2 class="text-lg font-bold text-gray-900">Persetujuan &amp; Waiver</h2>
        @if ($required)
            <p>Persetujuan wajib dicentang dan tanda tangan wajib diisi untuk setiap member.</p>
        @endif
    </div>
    <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 text-sm leading-6 text-gray-700">
        <h3 class="font-bold text-gray-900">{{ $terms['heading'] }}</h3>
        <p class="mt-2">{{ $terms['introduction'] }}</p>
        <ol class="mt-3 list-decimal space-y-2 pl-5">
            @foreach ($terms['items'] as $index => $item)
                <li wire:key="waiver-term-{{ $index }}">{{ $item }}</li>
            @endforeach
        </ol>
    </div>
    @error('waivers') <p role="alert" class="text-sm text-red-700">{{ $message }}</p> @enderror
    @foreach ($members->unique('id') as $member)
        <div wire:key="member-waiver-{{ $member->id }}" class="min-w-0 rounded-xl border border-gray-200 bg-white p-4">
            <h3 class="break-words text-base font-bold text-gray-900">{{ $member->name }}</h3>
            <label class="mt-3 flex cursor-pointer items-start gap-3 text-sm leading-6 text-gray-700">
                <input type="checkbox" wire:model="waivers.{{ $member->id }}.accepted"
                    id="waivers.{{ $member->id }}.accepted" aria-required="{{ $required ? 'true' : 'false' }}"
                    aria-describedby="consent-error-{{ $member->id }}"
                    aria-invalid="{{ $errors->has('waivers.'.$member->id.'.accepted') ? 'true' : 'false' }}"
                    class="mt-1 h-5 w-5 shrink-0 rounded border-gray-300 text-gray-900 focus:ring-gray-900">
                <span>{{ $records[$member->id]['consent_label'] ?? \App\MembershipWaiverTerms::CONSENT_LABEL }}</span>
            </label>
            @error('waivers.'.$member->id.'.accepted') <p id="consent-error-{{ $member->id }}" role="alert" class="mt-2 text-sm text-red-700">{{ $message }}</p> @enderror
            <p id="signature-label-{{ $member->id }}" class="mt-4 text-sm font-semibold text-gray-900">Tanda tangan {{ $member->name }}</p>
            <p id="signature-help-{{ $member->id }}" class="mt-1 text-xs leading-5 text-gray-500">Gambar dengan jari, stylus, atau mouse pada area di bawah.</p>
            <div id="waivers.{{ $member->id }}.signature" tabindex="-1" aria-describedby="signature-error-{{ $member->id }}">
            <div wire:ignore x-data="membershipSignature('waivers.{{ $member->id }}.signature')" class="mt-2">
                <canvas x-ref="canvas" width="800" height="480"
                    aria-labelledby="signature-label-{{ $member->id }}" aria-describedby="signature-help-{{ $member->id }}"
                    class="block aspect-[5/3] h-auto w-full touch-none rounded-lg border border-dashed border-gray-400 bg-white"
                    @pointerdown.prevent="start($event)" @pointermove.prevent="move($event)"
                    @pointerup="finish($event)" @pointercancel="finish($event)" @lostpointercapture="finish($event)">
                    Area tanda tangan. Gunakan perangkat penunjuk untuk menggambar.
                </canvas>
                <div class="mt-2 flex flex-wrap items-center justify-between gap-2">
                    <span class="text-xs text-gray-500" x-text="hasInk ? 'Tanda tangan terisi' : 'Belum diisi'" aria-live="polite"></span>
                    <button type="button" @click="clear()" class="min-h-11 rounded-lg bg-red-50 px-3 py-2 text-sm font-semibold text-red-700 transition-colors hover:bg-red-100 active:bg-red-200 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-700">Hapus tanda tangan</button>
                </div>
            </div>
            @error('waivers.'.$member->id.'.signature') <p id="signature-error-{{ $member->id }}" role="alert" class="mt-2 text-sm text-red-700">{{ $message }}</p> @enderror
            </div>
        </div>
    @endforeach
</section>
