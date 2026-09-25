<?php

namespace App\Models;

use Database\Factories\MembershipOperationalRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MembershipOperationalRequest extends Model
{
    /** @use HasFactory<MembershipOperationalRequestFactory> */
    use HasFactory;

    protected $fillable = ['submission_token', 'requested_by', 'requested_by_name', 'status', 'reason', 'requested_at', 'decided_by', 'decided_at', 'rejection_reason', 'snapshot', 'documents'];

    protected function casts(): array
    {
        return ['snapshot' => 'array', 'documents' => 'array', 'requested_at' => 'datetime', 'decided_at' => 'datetime'];
    }

    public function membership(): HasOne
    {
        return $this->hasOne(Membership::class, 'operational_request_id');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
