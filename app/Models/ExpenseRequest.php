<?php

namespace App\Models;

use Database\Factories\ExpenseRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpenseRequest extends Model
{
    /** @use HasFactory<ExpenseRequestFactory> */
    use HasFactory;

    protected $fillable = [
        'source', 'requested_by', 'requested_by_name', 'shift', 'description', 'amount',
        'expense_date', 'status', 'requested_at', 'decided_by', 'decided_at',
        'rejection_reason', 'expense_id', 'beverage_sale_id',
    ];

    protected $attributes = ['status' => 'pending'];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'expense_date' => 'date', 'requested_at' => 'datetime', 'decided_at' => 'datetime'];
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
