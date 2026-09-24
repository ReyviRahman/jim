@props(['invoice' => null, 'image' => null])

<div class="mb-4">
    <label for="invoice-image" class="block mb-2 text-sm font-medium text-heading">Foto invoice, opsional</label>
    <input id="invoice-image" type="file" wire:model="image" accept="image/jpeg,image/png,image/webp">
    <p class="text-sm text-body">JPG, PNG, atau WebP. Maksimal 10 MB.</p>
    <p wire:loading wire:target="image" role="status">Mengunggah foto...</p>
    @error('image') <p role="alert" class="text-red-600">{{ $message }}</p> @enderror
    @if ($image && ! $errors->has('image'))
        <img src="{{ $image->temporaryUrl() }}" alt="Pratinjau foto invoice" class="h-24 rounded-md">
    @endif
    @if ($invoice?->image_path)
        <a href="{{ route('admin.beverages.invoice.image', $invoice) }}" target="_blank" rel="noopener noreferrer" class="text-blue-700 underline">Lihat foto tersimpan</a>
    @endif
</div>
