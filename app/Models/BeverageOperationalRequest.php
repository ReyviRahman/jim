<?php

namespace App\Models;

use Database\Factories\BeverageOperationalRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BeverageOperationalRequest extends Model
{
    /** @use HasFactory<BeverageOperationalRequestFactory> */
    use HasFactory;

    protected $fillable = ['requested_by', 'nama_staff', 'shift', 'reason', 'status', 'requested_at', 'decided_by', 'decided_at', 'rejection_reason', 'deposit_beverage_id', 'deposit_amount', 'total'];

    protected function casts(): array
    {
        return ['requested_at' => 'datetime', 'decided_at' => 'datetime', 'total' => 'integer', 'deposit_amount' => 'integer'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(BeverageOperationalRequestItem::class, 'request_id');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function sales(): HasMany
    {
        return $this->hasMany(BeverageSale::class, 'operational_request_id');
    }
}
