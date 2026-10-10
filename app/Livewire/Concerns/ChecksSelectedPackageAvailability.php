<?php

namespace App\Livewire\Concerns;

use App\Models\GymPackage;
use Illuminate\Validation\ValidationException;

trait ChecksSelectedPackageAvailability
{
    protected function validateSelectedPackageAvailability(): void
    {
        $fields = [];
        if (in_array($this->registration_type, ['membership', 'visit', 'bundle_pt_membership'], true)) {
            $fields[] = 'gym_package_id';
        }
        if (in_array($this->registration_type, ['pt', 'bundle_pt_membership'], true)) {
            $fields[] = 'pt_package_id';
        }
        foreach ($fields as $field) {
            if ($this->{$field} && ! GymPackage::availableNow()->whereKey($this->{$field})->exists()) {
                throw ValidationException::withMessages([$field => 'Paket tidak tersedia pada jam ini. Silakan pilih paket lain.']);
            }
        }
    }
}
