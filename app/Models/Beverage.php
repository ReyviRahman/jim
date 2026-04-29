<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Beverage extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'nama_produk',
        'harga_modal',
        'harga_jual',
        'stok_sekarang',
    ];

    protected function casts(): array
    {
        return [
            'harga_modal' => 'integer',
            'harga_jual' => 'integer',
            'stok_sekarang' => 'integer',
        ];
    }

    public function restocks(): HasMany
    {
        return $this->hasMany(BeverageRestock::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(BeverageSale::class);
    }
}