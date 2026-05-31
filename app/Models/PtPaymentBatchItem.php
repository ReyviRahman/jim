<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PtPaymentBatchItem extends Model
{
    protected $fillable = [
        'pt_payment_batch_id',
        'pt_booking_id',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(PtPaymentBatch::class, 'pt_payment_batch_id');
    }

    public function ptBooking(): BelongsTo
    {
        return $this->belongsTo(PtBooking::class);
    }
}
