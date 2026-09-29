<?php

namespace App\Livewire\Concerns;

use App\MembershipAddonInput;
use Carbon\CarbonImmutable;

trait HasMembershipAddon
{
    public string $has_addon = '';

    public string $addon_name = '';

    public $addon_duration_months = '';

    public $addon_duration_weeks = '';

    public $addon_duration_days = '';

    public function updatedHasMembershipAddon(string $property): void
    {
        if (($property === 'registration_type' && $this->registration_type !== 'pt')
            || ($property === 'has_addon' && $this->has_addon !== 'yes')) {
            $this->reset('addon_name', 'addon_duration_months', 'addon_duration_weeks', 'addon_duration_days');
            $this->resetValidation(['addon_name', 'addon_duration_months', 'addon_duration_weeks', 'addon_duration_days']);
            if ($this->registration_type !== 'pt') {
                $this->reset('has_addon');
                $this->resetValidation('has_addon');
            }
        }
    }

    /** @return array{name: string, duration_months: int, duration_weeks: int, duration_days: int}|null */
    private function validatedAddon(): ?array
    {
        return app(MembershipAddonInput::class)->validate((string) $this->registration_type, $this->all());
    }

    public function getAddonEndDateProperty(): ?string
    {
        if (! $this->start_date || ! $this->is_active || $this->has_addon !== 'yes') {
            return null;
        }
        $days = (int) $this->addon_duration_months * 30 + (int) $this->addon_duration_weeks * 7 + (int) $this->addon_duration_days;
        if ($days < 1 || $days > 36500) {
            return null;
        }
        try {
            return CarbonImmutable::parse($this->start_date)->addDays($days - 1)->format('d/m/Y');
        } catch (\Throwable) {
            return null;
        }
    }
}
