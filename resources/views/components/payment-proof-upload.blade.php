@props([
    'model',
    'proof' => null,
    'existingUrl' => null,
    'errorKey' => null,
    'label' => 'Bukti Pembayaran',
    'required' => true,
])

@php
    $inputId = 'payment-proof-'.str_replace(['.', '[', ']'], '-', $model);
    $validationKey = $errorKey ?: $model;
@endphp

<div {{ $attributes->merge(['class' => 'space-y-2']) }}>
    <label for="{{ $inputId }}" class="block text-sm font-medium text-heading">
        {{ $label }}@if($required)<span class="text-red-500">*</span>@endif
    </label>

    @if($proof instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile && $proof->isPreviewable())
        <a href="{{ $proof->temporaryUrl() }}" target="_blank" rel="noopener noreferrer" class="inline-flex">
            <img src="{{ $proof->temporaryUrl() }}" alt="Preview {{ $label }}" class="h-24 w-24 rounded-md border border-default-medium object-cover shadow-xs">
        </a>
    @elseif($existingUrl)
        <a href="{{ $existingUrl }}" target="_blank" rel="noopener noreferrer" class="inline-flex">
            <img src="{{ $existingUrl }}" alt="{{ $label }} saat ini" class="h-24 w-24 rounded-md border border-default-medium object-cover shadow-xs">
        </a>
    @endif

    <input
        id="{{ $inputId }}"
        type="file"
        wire:model="{{ $model }}"
        accept="image/jpeg,image/png,image/webp"
        class="block w-full cursor-pointer rounded-md border border-default-medium bg-white text-sm text-heading shadow-xs file:mr-4 file:border-0 file:bg-neutral-secondary-medium file:px-4 file:py-2 file:text-sm file:font-medium file:text-heading hover:file:bg-gray-200 focus:border-brand focus:ring-brand"
    >

    <div wire:loading wire:target="{{ $model }}" class="text-xs font-medium text-brand-strong">
        Mengunggah gambar...
    </div>

    <p class="text-xs text-body">JPG, JPEG, PNG, atau WEBP. Maksimal 10 MB.</p>

    @error($validationKey)
        <span class="block text-xs text-red-500">{{ $message }}</span>
    @enderror
</div>
