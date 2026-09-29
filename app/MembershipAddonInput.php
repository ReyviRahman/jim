<?php

namespace App;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class MembershipAddonInput
{
    /** @param array<string, mixed> $input
     * @return array{name: string, duration_months: int, duration_weeks: int, duration_days: int}|null
     */
    public function validate(string $type, array $input): ?array
    {
        if ($type !== 'pt') {
            return null;
        }
        Validator::make($input, ['has_addon' => ['required', 'in:yes,no']], [
            'has_addon.required' => 'Pilih Ya atau Tidak untuk add-on.',
            'has_addon.in' => 'Pilih Ya atau Tidak untuk add-on.',
        ])->validate();
        if ($input['has_addon'] === 'no') {
            return null;
        }
        $input['addon_name'] = is_string($input['addon_name'] ?? null) ? trim($input['addon_name']) : ($input['addon_name'] ?? null);
        $rules = ['addon_name' => ['required', 'string', 'max:255']];
        foreach (['months', 'weeks', 'days'] as $unit) {
            $field = 'addon_duration_'.$unit;
            $input[$field] = blank($input[$field] ?? null) ? 0 : $input[$field];
            $rules[$field] = ['required', 'integer', 'min:0', 'max:36500'];
        }
        $data = Validator::make($input, $rules, [
            'addon_name.required' => 'Nama add-on wajib diisi.',
            '*.integer' => 'Durasi harus berupa bilangan bulat.',
            '*.min' => 'Durasi tidak boleh negatif.',
        ])->validate();
        $days = (int) $data['addon_duration_months'] * 30 + (int) $data['addon_duration_weeks'] * 7 + (int) $data['addon_duration_days'];
        if ($days < 1 || $days > 36500) {
            throw ValidationException::withMessages(['addon_duration_months' => 'Total durasi add-on harus antara 1 dan 36.500 hari.']);
        }

        return ['name' => $data['addon_name'], 'duration_months' => (int) $data['addon_duration_months'],
            'duration_weeks' => (int) $data['addon_duration_weeks'], 'duration_days' => (int) $data['addon_duration_days']];
    }
}
