<?php

namespace App\Livewire\Concerns;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Validator as ValidationValidator;

trait ValidatesPackageDuration
{
    public $duration_months = '';

    public $duration_weeks = '';

    public $duration_days = '';

    /** @return array{duration_months: int, duration_weeks: int, duration_days: int} */
    protected function validatedDuration(): array
    {
        $values = [];
        $rules = [];
        foreach (['duration_months', 'duration_weeks', 'duration_days'] as $field) {
            $values[$field] = blank($this->{$field}) ? 0 : $this->{$field};
            $rules[$field] = ['required', 'integer', 'min:0', 'max:65535'];
        }
        $validator = Validator::make($values, $rules, [
            '*.integer' => 'Durasi harus berupa bilangan bulat.',
            '*.min' => 'Durasi tidak boleh negatif.',
            '*.max' => 'Durasi maksimal 65535 per kolom.',
        ]);
        $validator->after(function (ValidationValidator $validator) use ($values): void {
            if (! $validator->errors()->any() && array_sum($values) == 0) {
                $validator->errors()->add('duration_months', 'Isi minimal salah satu durasi: bulan, minggu, atau hari lebih dari 0.');
            }
        });

        return array_map('intval', $validator->validate());
    }
}
