<?php

namespace App\Models;

use Database\Factories\ShiftFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Shift extends Model
{
    /** @use HasFactory<ShiftFactory> */
    use HasFactory;

    public const ROLE_LABELS = [
        'admin' => 'Manager',
        'pt' => 'Personal Trainer',
        'kasir_gym' => 'Kasir Gym',
        'kasir_minum' => 'Kasir Minuman',
        'sales' => 'Sales',
        'cleaning_service' => 'Cleaning Service',
    ];

    protected $fillable = ['code', 'name', 'start_time', 'end_time', 'role'];

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'shift');
    }

    public function scopeForRole(Builder $query, string $role): Builder
    {
        return $query->where('role', $role);
    }

    /**
     * @param  array<class-string<Model>>  $snapshotModels
     * @return Collection<string, string>
     */
    public static function filterOptions(array $snapshotModels): Collection
    {
        $names = static::query()->orderBy('start_time')->orderBy('id')->pluck('name');

        foreach ($snapshotModels as $model) {
            $names = $names->concat($model::query()->whereNotNull('shift')->distinct()->pluck('shift'));
        }

        return $names->filter(fn (string $name): bool => filled($name))
            ->unique(fn (string $name): string => mb_strtolower($name))
            ->mapWithKeys(fn (string $name): array => [mb_strtolower($name) => $name]);
    }
}
