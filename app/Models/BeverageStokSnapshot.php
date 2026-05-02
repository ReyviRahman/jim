<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BeverageStokSnapshot extends Model
{
    use HasFactory;

    protected $fillable = [
        'beverage_id',
        'tanggal',
        'tipe',
        'jumlah',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'tipe' => 'string',
            'jumlah' => 'integer',
        ];
    }

    public function beverage(): BelongsTo
    {
        return $this->belongsTo(Beverage::class);
    }
}
