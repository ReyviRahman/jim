<?php

namespace App\Actions;

use App\MembershipWaiverTerms;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class StoreMembershipWaivers
{
    /**
     * @param  Collection<int, User>  $members
     * @param  array<int, array{accepted?: bool, signature?: string|null}>  $input
     * @return array<int, array{accepted: bool, signature: string|null}>
     */
    public function validate(Collection $members, array $input): array
    {
        $ids = $members->modelKeys();
        $validated = Validator::make(['waivers' => $input], [
            'waivers' => ['array:'.implode(',', $ids)],
            'waivers.*' => ['array:accepted,signature'],
            'waivers.*.accepted' => ['sometimes', 'boolean'],
            'waivers.*.signature' => ['nullable', 'string', 'max:1398126'],
        ], [
            'waivers.array' => 'Member persetujuan tidak sesuai dengan daftar member.',
            'waivers.*.signature.max' => 'Tanda tangan maksimal 1 MB. Hapus lalu gambar ulang.',
        ])->validate()['waivers'];

        $result = [];
        foreach ($members->unique('id') as $member) {
            $entry = $validated[$member->id] ?? [];
            $signature = $entry['signature'] ?? null;
            $result[$member->id] = [
                'accepted' => (bool) ($entry['accepted'] ?? false),
                'signature' => filled($signature) ? $this->decodeSignature($signature, $member->id) : null,
            ];
        }

        return $result;
    }

    /**
     * @param  array<int, array{accepted: bool, signature: string|null}>  $validated
     * @return list<string>
     */
    public function execute(Membership $membership, array $validated, ?int $adminId, bool $update = false): array
    {
        $members = $membership->members()->get()->prepend($membership->user()->first())->filter()->unique('id');
        if (array_diff(array_keys($validated), $members->modelKeys()) !== []) {
            throw ValidationException::withMessages(['waivers' => 'Member persetujuan tidak sesuai dengan membership.']);
        }

        $paths = [];
        try {
            foreach ($members as $member) {
                $existing = $update ? $membership->waivers()->where('user_id', $member->id)->first() : null;
                $entry = $validated[$member->id] ?? ['accepted' => false, 'signature' => null];
                $path = null;
                if ($existing?->signature_path && Storage::disk('local')->exists($existing->signature_path)
                    && Storage::disk('local')->get($existing->signature_path) === $entry['signature']) {
                    $path = $existing->signature_path;
                } elseif ($entry['signature'] !== null) {
                    $path = 'membership-waivers/'.$membership->id.'/'.Str::uuid().'.png';
                    $paths[] = $path;
                    if (! Storage::disk('local')->put($path, $entry['signature'])) {
                        throw new RuntimeException('Tanda tangan gagal disimpan. Silakan coba lagi.');
                    }
                }

                if ($existing && $existing->accepted === $entry['accepted'] && $existing->signature_path === $path) {
                    continue;
                }

                $attributes = [
                    'user_id' => $member->id,
                    'member_name' => $member->name,
                    'accepted' => $entry['accepted'],
                    'signature_path' => $path,
                    'recorded_at' => now(),
                    'admin_id' => $adminId,
                    'terms_snapshot' => $existing?->terms_snapshot ?? MembershipWaiverTerms::snapshot(),
                    'consent_label' => $existing?->consent_label ?? MembershipWaiverTerms::CONSENT_LABEL,
                ];
                if ($existing) {
                    $existing->update($attributes);
                } else {
                    $membership->waivers()->create($attributes);
                }
            }
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($paths);
            throw $exception;
        }

        return $paths;
    }

    private function decodeSignature(string $signature, int $memberId): string
    {
        $message = 'Tanda tangan harus berupa PNG maksimal 1 MB. Hapus lalu gambar ulang.';
        if (! str_starts_with($signature, 'data:image/png;base64,')) {
            throw ValidationException::withMessages(['waivers.'.$memberId.'.signature' => $message]);
        }

        $bytes = base64_decode(substr($signature, 22), true);
        $size = is_string($bytes) ? @getimagesizefromstring($bytes) : false;
        if (! is_string($bytes) || strlen($bytes) > 1048576 || ! $size
            || $size[2] !== IMAGETYPE_PNG || $size[0] > 4096 || $size[1] > 2048) {
            throw ValidationException::withMessages(['waivers.'.$memberId.'.signature' => $message]);
        }

        $image = @imagecreatefromstring($bytes);
        if ($image === false) {
            throw ValidationException::withMessages(['waivers.'.$memberId.'.signature' => $message]);
        }

        imagesavealpha($image, true);
        ob_start();
        imagepng($image);
        $png = ob_get_clean();
        imagedestroy($image);

        if (! is_string($png) || strlen($png) > 1048576) {
            throw ValidationException::withMessages(['waivers.'.$memberId.'.signature' => $message]);
        }

        return $png;
    }
}
