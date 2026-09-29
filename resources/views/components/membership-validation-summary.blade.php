@props(['members'])
@if($errors->any())
    <div data-membership-validation-summary tabindex="-1" role="alert" class="lg:col-span-3 rounded-md border-2 border-red-500 bg-red-50 p-4 text-sm text-red-800">
        <h2 class="font-bold">Belum tersimpan. Periksa isian berikut.</h2>
        <p class="mt-1">Klik pesan untuk menuju isian yang perlu diperbaiki. Data yang sudah diisi tetap tersimpan di formulir.</p>
        <ul class="mt-3 list-disc space-y-2 pl-5">
            @foreach($errors->messages() as $field => $messages)
                @php
                    $label = \App\MembershipFormValidation::attributes()[$field] ?? '';
                    if (preg_match('/^(memberPhotos|waivers)\.(\d+)(?:\.(accepted|signature))?$/', $field, $match)) {
                        $member = $members->firstWhere('id', (int) $match[2]);
                        $label = ($match[1] === 'memberPhotos' ? 'Foto profil' : (($match[3] ?? '') === 'accepted' ? 'Persetujuan' : 'Tanda tangan')).' — '.($member?->name ?? 'Member');
                    }
                @endphp
                @foreach($messages as $message)
                    <li>
                        <button type="button" data-validation-target="{{ $field }}" x-on:click="focusField($el.dataset.validationTarget)" class="text-left underline decoration-red-400 underline-offset-2 hover:text-red-950">
                            @if($label)<span class="font-semibold">{{ $label }}:</span>@endif {{ $message }}
                        </button>
                    </li>
                @endforeach
            @endforeach
        </ul>
    </div>
@endif
