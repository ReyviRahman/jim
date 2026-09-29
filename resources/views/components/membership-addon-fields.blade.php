@if($registration_type === 'pt')
    <fieldset class="md:col-span-2 rounded-md border border-default bg-neutral-primary-soft p-4" wire:key="membership-addon-fields">
        <legend class="px-1 text-sm font-semibold text-heading">Mendapatkan add-on? <span class="text-red-600">*</span></legend>
        <div class="flex gap-6">
            <label class="flex items-center gap-2 text-sm text-heading"><input type="radio" wire:model.live="has_addon" value="yes" name="has_addon"> Ya</label>
            <label class="flex items-center gap-2 text-sm text-heading"><input type="radio" wire:model.live="has_addon" value="no" name="has_addon"> Tidak</label>
        </div>
        @error('has_addon') <p role="alert" class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
        @if($has_addon === 'yes')
            <div class="mt-4 grid gap-4 sm:grid-cols-3">
                <div class="sm:col-span-3">
                    <label for="addon_name" class="mb-2 block text-sm font-medium text-heading">Nama add-on</label>
                    <input id="addon_name" wire:model="addon_name" maxlength="255" placeholder="Membership 1 Monthly Pass" class="w-full rounded-md border border-default-medium bg-neutral-primary-soft px-3 py-2 text-heading">
                    @error('addon_name') <p role="alert" class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                @foreach(['months' => 'Bulan', 'weeks' => 'Minggu', 'days' => 'Hari'] as $unit => $label)
                    <div wire:key="addon-duration-{{ $unit }}">
                        <label for="addon_duration_{{ $unit }}" class="mb-2 block text-sm font-medium text-heading">{{ $label }}</label>
                        <input type="number" id="addon_duration_{{ $unit }}" wire:model.live="addon_duration_{{ $unit }}" min="0" step="1" placeholder="0" class="w-full rounded-md border border-default-medium bg-neutral-primary-soft px-3 py-2 text-heading">
                        @error('addon_duration_'.$unit) <p role="alert" class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                @endforeach
                <div class="sm:col-span-3 text-sm text-body">
                    <p>1 bulan = 30 hari. 1 minggu = 7 hari.</p>
                    @if($this->addonEndDate)
                        <p>Mulai: {{ $this->getFormattedDate($start_date) }} · Selesai: {{ $this->addonEndDate }}</p>
                    @else
                        <p>Tanggal mengikuti aktivasi PT setelah durasi diisi.</p>
                    @endif
                    <p>Add-on menunggu persetujuan Manager. Akses gym tersedia setelah disetujui dan PT lunas serta diaktifkan.</p>
                </div>
            </div>
        @endif
    </fieldset>
@endif
