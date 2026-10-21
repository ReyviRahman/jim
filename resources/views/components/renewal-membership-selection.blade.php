<div>
    @if ($this->renewalCandidates->count() > 1)
        <button type="button" wire:click="openRenewalSelection" class="mt-4 rounded-lg border border-default-medium px-4 py-2 text-sm font-medium">
            Pilih paket yang di-renewal
            @if ($this->confirmedRenewalIds !== null)
                ({{ count($this->confirmedRenewalIds) }} dipilih)
            @endif
        </button>
    @endif
    @error('selectedRenewalIds') <p role="alert" class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror

    @if ($this->showRenewalSelection)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4" x-data x-trap.inert.noscroll="true" wire:key="renewal-selection-modal">
            <section role="dialog" aria-modal="true" aria-labelledby="renewal-selection-title" class="max-h-[85vh] w-full max-w-2xl overflow-y-auto rounded-xl bg-white p-6 text-gray-900 shadow-xl">
                <h2 id="renewal-selection-title" class="text-lg font-semibold">Pilih paket yang di-renewal</h2>
                <p class="mt-2 text-sm text-gray-600">Ada beberapa paket terakhir milik member yang dipilih. Centang paket yang akan dihubungkan ke pembelian renewal ini. Tanggal akhir dan sisa sesi paket lama tetap.</p>
                <div class="my-4 space-y-3">
                    @foreach ($this->renewalCandidates as $membership)
                        <label wire:key="renewal-candidate-{{ $membership->id }}" class="flex cursor-pointer gap-3 rounded-lg border border-gray-200 p-4">
                            <input type="checkbox" wire:model="selectedRenewalIds" value="{{ $membership->id }}" class="mt-1 h-4 w-4">
                            <span>
                                <span class="block font-semibold">#{{ $membership->id }} · {{ $membership->gymPackage?->name ?? $membership->ptPackage?->name ?? $membership->package_name ?? $membership->type }}</span>
                                <span class="block text-sm">{{ $membership->members->concat([$membership->user])->filter()->unique('id')->pluck('name')->join(', ') }}</span>
                                <span class="block text-sm text-gray-600">
                                    Status: {{ $membership->status }}
                                    @if (in_array($membership->type, ['pt', 'bundle_pt_membership'], true))
                                        · Sisa sesi: {{ $membership->remaining_sessions ?? 0 }}
                                    @endif
                                </span>
                                <span class="block text-sm text-gray-600">Berakhir gym: {{ $membership->membership_end_date?->format('d/m/Y') ?? '-' }} · PT: {{ $membership->pt_end_date?->format('d/m/Y') ?? '-' }}</span>
                                @if ($membership->renewal_from)
                                    <span class="block text-sm text-amber-700">Hubungan renewal #{{ $membership->renewal_from }} akan diganti.</span>
                                @endif
                            </span>
                        </label>
                    @endforeach
                </div>
                @error('selectedRenewalIds') <p role="alert" class="mb-3 text-sm text-red-600">{{ $message }}</p> @enderror
                @error('selectedRenewalIds.*') <p role="alert" class="mb-3 text-sm text-red-600">{{ $message }}</p> @enderror
                <div class="flex justify-end gap-3">
                    <button type="button" wire:click="$set('showRenewalSelection', false)" class="rounded-lg border border-gray-300 px-4 py-2">Batal</button>
                    <button type="button" wire:click="confirmRenewalSelection" wire:loading.attr="disabled" class="rounded-lg bg-yellow-400 px-4 py-2 font-semibold">Gunakan pilihan</button>
                </div>
            </section>
        </div>
    @endif
</div>
