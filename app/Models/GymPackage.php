<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
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
        'available_from',
        'available_until',
    ];

    protected function casts(): array
    {
        return [
            'duration_months' => 'integer',
            'duration_weeks' => 'integer',
            'duration_days' => 'integer',
        ];
    }

    public function scopeAvailableNow(Builder $query): Builder
    {
        $time = now(config('app.timezone'))->format('H:i:s');

        return $query->where(function (Builder $query) use ($time): void {
            $query->where(fn (Builder $query) => $query->whereNull('available_from')->whereNull('available_until'))
                ->orWhere(function (Builder $query) use ($time): void {
                    $query->whereColumn('available_from', '<', 'available_until')
                        ->where('available_from', '<=', $time)->where('available_until', '>', $time);
                })
                ->orWhere(function (Builder $query) use ($time): void {
                    $query->whereColumn('available_from', '>', 'available_until')
                        ->where(fn (Builder $query) => $query->where('available_from', '<=', $time)->orWhere('available_until', '>', $time));
                });
        });
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
