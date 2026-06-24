<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PtPaymentBatch extends Model
{
    protected $fillable = [
        'pt_id',
        'date_start',
        'date_end',
        'paid_by',
        'potongan',
        'keterangan_potongan',
    ];

    protected function casts(): array
    {
        return [
            'date_start' => 'date',
            'date_end' => 'date',
        ];
    }

    public function pt(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pt_id');
    }

    public function paidBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PtPaymentBatchItem::class);
    }
}
