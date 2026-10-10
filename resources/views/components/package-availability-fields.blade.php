<fieldset class="mb-6">
    <legend class="mb-2 text-sm font-semibold text-heading">Rentang Jam Tampil (Opsional)</legend>
    <p class="mb-3 text-sm text-gray-500">Kosongkan keduanya agar paket tampil sepanjang hari. Berlaku setiap hari dalam WIB, mulai jam awal hingga sebelum jam selesai. Jika jam selesai lebih kecil, rentang berlanjut ke hari berikutnya.</p>
    @foreach(['available_from' => 'Jam Mulai', 'available_until' => 'Jam Selesai'] as $field => $label)
        <div class="mb-3" wire:key="availability-{{ $field }}">
            <label for="{{ $field }}" class="mb-2 block text-sm font-medium text-heading">{{ $label }}</label>
            <input type="time" id="{{ $field }}" wire:model="{{ $field }}" step="60" class="bg-white border border-default-medium text-heading text-sm rounded-md block w-full px-3 py-2.5">
            @error($field) <p role="alert" class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
        </div>
    @endforeach
</fieldset>
