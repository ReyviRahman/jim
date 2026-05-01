<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BeverageInvoiceItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'beverage_invoice_id',
        'nama_barang',
        'qty',
        'harga_perdus',
        'biaya_ppn',
        'total',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'integer',
            'harga_perdus' => 'integer',
            'biaya_ppn' => 'integer',
            'total' => 'integer',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(BeverageInvoice::class, 'beverage_invoice_id');
    }
}
