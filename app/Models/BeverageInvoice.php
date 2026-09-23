<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BeverageInvoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'image_path',
        'stock_posted_at',

        'no_faktur',
        'tanggal_order',
        'tanggal_menerima',
        'diterima_oleh',
        'status',
        'metode_pembayaran',
    ];

    protected function casts(): array
    {
        return [
            'stock_posted_at' => 'datetime',
            'tanggal_order' => 'date',
            'tanggal_menerima' => 'date',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(BeverageInvoiceItem::class);
    }

    public function getGrandTotalAttribute(): int
    {
        return $this->items->sum('total');
    }

    public function getTotalPpnAttribute(): int
    {
        return $this->items->sum('biaya_ppn');
    }

    public function getTotalQtyAttribute(): int
    {
        return $this->items->sum('qty');
    }
}
