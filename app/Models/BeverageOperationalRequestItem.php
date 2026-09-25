<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BeverageOperationalRequestItem extends Model
{
    protected $fillable = ['beverage_id', 'nama_produk', 'jumlah_beli', 'harga_satuan'];

    protected function casts(): array
    {
        return ['jumlah_beli' => 'integer', 'harga_satuan' => 'integer'];
    }
}
