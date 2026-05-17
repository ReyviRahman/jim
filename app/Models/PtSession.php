<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PtSession extends Model
{
    protected $fillable = [
        'period_id',
        'membership_id',
        'member_id',
        'pt_id',
        'price',
        'category',
        'sale_category',
        'initial_sessions',
        'added_sessions',
        'total_sessions',
        'used_sessions',
        'expired_sessions',
        'remaining_sessions',
        'nominal_per_session',
        'total_nominal',
    ];

    protected $casts = [
        'price' => 'decimal:0',
        'initial_sessions' => 'integer',
        'added_sessions' => 'integer',
        'total_sessions' => 'integer',
        'used_sessions' => 'integer',
        'expired_sessions' => 'integer',
        'remaining_sessions' => 'integer',
        'nominal_per_session' => 'decimal:0',
        'total_nominal' => 'decimal:0',
    ];

    public function period(): BelongsTo
    {
        return $this->belongsTo(Period::class);
    }

    public function membership(): BelongsTo
    {
        return $this->belongsTo(Membership::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(User::class, 'member_id');
    }

    public function pt(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pt_id');
    }
}
