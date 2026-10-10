<?php

namespace App\Livewire\Concerns;

use Illuminate\Support\Facades\Validator;

trait ValidatesPackageAvailability
{
    public $available_from = '';

    public $available_until = '';

    /** @return array{available_from: ?string, available_until: ?string} */
    protected function validatedAvailability(): array
    {
        return Validator::make([
            'available_from' => blank($this->available_from) ? null : $this->available_from,
            'available_until' => blank($this->available_until) ? null : $this->available_until,
        ], [
            'available_from' => ['nullable', 'required_with:available_until', 'date_format:H:i'],
            'available_until' => ['nullable', 'required_with:available_from', 'date_format:H:i', 'different:available_from'],
        ], [
            'required_with' => 'Isi jam mulai dan jam selesai untuk membatasi waktu tampil paket.',
            'date_format' => 'Gunakan format jam HH:MM.',
            'different' => 'Jam selesai harus berbeda dari jam mulai.',
        ])->validate();
    }
}
