<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BeverageInvoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'no_faktur',
        'tanggal_order',
        'tanggal_menerima',
        'diterima_oleh',
        'supplier_name',
        'status',
        'metode_pembayaran',
    ];

    protected function casts(): array
    {
        return [
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
