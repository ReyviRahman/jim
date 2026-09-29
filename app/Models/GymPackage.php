<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GymPackage extends Model
{
    protected $attributes = [
        'duration_months' => 0,
        'duration_weeks' => 0,
        'duration_days' => 0,
    ];

    protected $fillable = [
        'type',
        'name',
        'category',
        'max_members',
        'pt_sessions',
        'price',
        'normal_price',
        'net_price',
        'unrecommended_price',
        'discount',
        'is_active',
        'duration_months',
        'duration_weeks',
        'duration_days',
    ];

    protected function casts(): array
    {
        return [
            'duration_months' => 'integer',
            'duration_weeks' => 'integer',
            'duration_days' => 'integer',
        ];
    }

    public function durationInDays(): int
    {
        return $this->duration_months * 30 + $this->duration_weeks * 7 + $this->duration_days;
    }

    public function durationLabel(): string
    {
        $parts = [];
        foreach (['duration_months' => 'bulan', 'duration_weeks' => 'minggu', 'duration_days' => 'hari'] as $field => $unit) {
            if ($this->{$field} > 0) {
                $parts[] = $this->{$field}.' '.$unit;
            }
        }

        return $parts ? implode(' ', $parts).' ('.$this->durationInDays().' hari)' : 'Durasi belum diisi';
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }
}
