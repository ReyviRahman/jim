<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DepositBeverage extends Model
{
    use HasFactory;

    protected $fillable = [
        'beverage_sale_id',
        'nama_pelanggan',
        'nominal',
        'sisa_nominal',
        'is_used',
    ];

    protected function casts(): array
    {
        return [
            'nominal' => 'integer',
            'sisa_nominal' => 'integer',
            'is_used' => 'boolean',
        ];
    }

    public function beverageSale(): BelongsTo
    {
        return $this->belongsTo(BeverageSale::class);
    }
}
