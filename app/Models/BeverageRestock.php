<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BeverageRestock extends Model
{
    use HasFactory;

    protected $fillable = [
        'beverage_id',
        'tanggal',
        'jumlah_tambah',
        'keterangan',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'jumlah_tambah' => 'integer',
        ];
    }

    public function beverage(): BelongsTo
    {
        return $this->belongsTo(Beverage::class);
    }
}