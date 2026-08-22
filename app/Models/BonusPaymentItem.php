<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BonusPaymentItem extends Model
{
    protected $fillable = [
        'bonus_payment_id',
        'membership_id',
        'member_name',
        'package_name',
        'nominal',
        'nominal_akhir',
        'payment_date',
    ];

    protected function casts(): array
    {
        return [
            'nominal' => 'decimal:2',
            'nominal_akhir' => 'decimal:2',
            'payment_date' => 'date',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(BonusPayment::class, 'bonus_payment_id');
    }

    public function membership(): BelongsTo
    {
        return $this->belongsTo(Membership::class);
    }
}
