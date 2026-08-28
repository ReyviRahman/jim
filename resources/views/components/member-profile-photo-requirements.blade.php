@props([
    'members',
    'photos' => [],
])

<div {{ $attributes->merge(['class' => 'rounded-md border border-default bg-neutral-primary-soft p-4 shadow-xs']) }}>
    <h6 class="mb-3 border-b border-default-medium pb-2 text-sm font-semibold text-heading">Foto Profil Member</h6>

    <div class="space-y-4">
        @foreach ($members as $member)
            @php($field = 'memberPhotos.'.$member->id)
            @php($uploadedPhoto = $photos[$member->id] ?? null)

            <div wire:key="member-profile-photo-{{ $member->id }}" class="flex flex-col gap-3 sm:flex-row sm:items-center">
                @if ($uploadedPhoto && $uploadedPhoto->isPreviewable())
                    <img class="h-16 w-16 shrink-0 rounded-full border-2 border-white object-cover shadow-sm" src="{{ $uploadedPhoto->temporaryUrl() }}" alt="Preview foto profil {{ $member->name }}">
                @elseif ($member->photo)
                    <img class="h-16 w-16 shrink-0 rounded-full border-2 border-white object-cover shadow-sm" src="{{ asset('storage/'.$member->photo) }}" alt="Foto profil {{ $member->name }}">
                @else
                    <img class="h-16 w-16 shrink-0 rounded-full border-2 border-white object-cover shadow-sm" src="https://ui-avatars.com/api/?name={{ urlencode($member->name) }}&background=random" alt="Avatar {{ $member->name }}">
                @endif

                <div class="min-w-0 flex-1">
                    <div class="font-semibold text-heading">{{ $member->name }}</div>
                    <div class="mb-2 truncate text-xs text-body">{{ $member->email }}</div>

                    @if (blank($member->photo))
                        <label for="member-photo-{{ $member->id }}" class="mb-1 block text-sm font-medium text-heading">
                            Upload Foto Profil <span class="text-red-500">*</span>
                        </label>
                        <input
                            id="member-photo-{{ $member->id }}"
                            type="file"
                            wire:model="memberPhotos.{{ $member->id }}"
                            accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp"
                            required
                            data-focus-on-invalid
                            data-required-message="Foto profil wajib di upload."
                            class="block w-full cursor-pointer rounded-base border border-default-medium bg-white text-sm text-heading shadow-xs focus:border-brand focus:ring-brand"
                        >
                        <p class="mt-1 text-xs text-body">JPG, JPEG, PNG, atau WebP; maksimal 10 MB. Foto otomatis dikompres.</p>
                        <div wire:loading wire:target="memberPhotos.{{ $member->id }}" class="mt-1 text-xs text-body">Mengunggah foto...</div>
                        @error($field) <span class="mt-1 block text-sm text-red-500">{{ $message }}</span> @enderror
                    @else
                        <p class="text-xs font-medium text-emerald-700">Foto profil sudah tersedia.</p>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</div>
