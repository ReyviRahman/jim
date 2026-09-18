<?php

namespace App\Models;

use Database\Factories\MembershipHoldFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MembershipHold extends Model
{
    /** @use HasFactory<MembershipHoldFactory> */
    use HasFactory;

    protected $fillable = [
        'membership_id', 'created_by', 'submission_token', 'months',
        'monthly_price', 'total_amount', 'previous_end_date', 'new_end_date',
    ];

    protected function casts(): array
    {
        return [
            'months' => 'integer',
            'monthly_price' => 'decimal:0',
            'total_amount' => 'decimal:0',
            'previous_end_date' => 'date',
            'new_end_date' => 'date',
        ];
    }

    public function membership(): BelongsTo
    {
        return $this->belongsTo(Membership::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(MembershipTransaction::class);
    }
}
