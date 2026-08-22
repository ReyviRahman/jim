<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BonusPayment extends Model
{
    protected $fillable = [
        'staff_user_id',
        'date_start',
        'date_end',
        'search_filter',
        'total_nominal_akhir',
        'bonus_percentage',
        'range_start',
        'range_end',
        'bonus_amount',
        'potongan',
        'keterangan_potongan',
        'net_amount',
        'paid_by',
        'paid_at',
    ];

    protected $attributes = [
        'potongan' => 0,
    ];

    protected function casts(): array
    {
        return [
            'date_start' => 'date',
            'date_end' => 'date',
            'total_nominal_akhir' => 'decimal:2',
            'bonus_percentage' => 'decimal:2',
            'bonus_amount' => 'decimal:2',
            'potongan' => 'decimal:2',
            'net_amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public function staffUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_user_id');
    }

    public function paidBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(BonusPaymentItem::class);
    }
}
