<?php

namespace App\Livewire\Concerns;

use App\Actions\StoreCompressedProfilePhoto;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

trait HandlesRequiredMemberProfilePhotos
{
    /** @var array<int, UploadedFile|null> */
    public array $memberPhotos = [];

    /**
     * @return Collection<int, User>
     */
    abstract protected function memberProfileUsers(): Collection;

    protected function validateRequiredMemberProfilePhotos(): void
    {
        $rules = [];
        $messages = [];

        foreach ($this->memberProfileUsers() as $member) {
            if (filled($member->photo)) {
                continue;
            }

            $field = 'memberPhotos.'.$member->getKey();
            $rules[$field] = [
                'required',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'mimetypes:image/jpeg,image/png,image/webp',
                'extensions:jpg,jpeg,png,webp',
                'max:10240',
            ];
            $messages[$field.'.required'] = 'Foto profil wajib di upload.';
            $messages[$field.'.image'] = 'Foto profil harus berupa gambar.';
            $messages[$field.'.mimes'] = 'Foto profil harus berformat JPG, JPEG, PNG, atau WebP.';
            $messages[$field.'.mimetypes'] = 'Tipe file foto profil tidak didukung.';
            $messages[$field.'.extensions'] = 'Ekstensi foto profil tidak didukung.';
            $messages[$field.'.max'] = 'Ukuran foto profil maksimal 10 MB.';
        }

        if ($rules !== []) {
            $this->validate($rules, $messages);
        }
    }

    /**
     * @return array<int, string>
     */
    protected function storeRequiredMemberProfilePhotos(StoreCompressedProfilePhoto $storeCompressedProfilePhoto): array
    {
        $storedPaths = [];

        try {
            foreach ($this->memberProfileUsers() as $member) {
                if (filled($member->photo)) {
                    continue;
                }

                $photo = $this->memberPhotos[$member->getKey()] ?? null;

                if (! $photo instanceof UploadedFile) {
                    throw new RuntimeException('Foto profil wajib di upload.');
                }

                $path = $storeCompressedProfilePhoto->execute($photo);
                $storedPaths[] = $path;

                if (User::query()->whereKey($member->getKey())->update(['photo' => $path]) !== 1) {
                    throw new RuntimeException('Foto profil member gagal diperbarui.');
                }
            }
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($storedPaths);

            throw $exception;
        }

        return $storedPaths;
    }
}
