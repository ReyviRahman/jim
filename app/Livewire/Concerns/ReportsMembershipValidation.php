<?php

namespace App\Livewire\Concerns;

use App\MembershipFormValidation;
use Illuminate\Validation\ValidationException;
use Throwable;

trait ReportsMembershipValidation
{
    protected function validationAttributes(): array
    {
        return MembershipFormValidation::attributes();
    }

    protected function messages(): array
    {
        return MembershipFormValidation::messages();
    }

    public function exception(Throwable $e, callable $stopPropagation): void
    {
        if ($e instanceof ValidationException) {
            $this->dispatch('membership-form-invalid', fields: array_keys($e->errors()));
        }
    }
}
