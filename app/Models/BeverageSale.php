<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BeverageSale extends Model
{
    use HasFactory;

    protected $fillable = [
        'beverage_id',
        'nama_staff',
        'waktu_transaksi',
        'shift',
        'jumlah_beli',
        'harga_satuan',
        'total_harga',
        'keterangan_bayar',
        'nama_penghutang',
        'is_lunas',
    ];

    protected function casts(): array
    {
        return [
            'waktu_transaksi' => 'datetime',
            'jumlah_beli' => 'integer',
            'harga_satuan' => 'integer',
            'total_harga' => 'integer',
        ];
    }

    public function beverage(): BelongsTo
    {
        return $this->belongsTo(Beverage::class);
    }
}