<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class BeverageSale extends Model
{
    use HasFactory;

    protected $fillable = [
        'beverage_id',
        'deposit_beverage_id',
        'deposit_amount',
        'save_deposit',
        'parent_beverage_sale_id',
        'nama_produk',
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
            'deposit_amount' => 'integer',
            'save_deposit' => 'integer',
        ];
    }

    public function beverage(): BelongsTo
    {
        return $this->belongsTo(Beverage::class);
    }

    public function depositBeverage(): BelongsTo
    {
        return $this->belongsTo(DepositBeverage::class);
    }

    public function parentBeverageSale(): BelongsTo
    {
        return $this->belongsTo(BeverageSale::class, 'parent_beverage_sale_id');
    }

    public function childBeverageSales(): HasMany
    {
        return $this->hasMany(BeverageSale::class, 'parent_beverage_sale_id');
    }

    public function changeDeposit(): HasOne
    {
        return $this->hasOne(DepositBeverage::class, 'beverage_sale_id');
    }
}
