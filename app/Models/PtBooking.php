<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PtBooking extends Model
{
    protected $fillable = [
        'membership_id',
        'member_id',
        'pt_id',
        'booking_date',
        'booking_time',
        'status',
        'type',
        'attendance',
        'notes',
        'cancelled_by',
        'cancelled_at',
        'cancellation_reason',
        'cancellation_requested_at',
        'rejected_at',
        'rejection_reason',
    ];

    protected $casts = [
        'booking_date' => 'date',
        'booking_time' => 'datetime',
        'cancelled_at' => 'datetime',
        'cancellation_requested_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

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

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function isCancellationPending(): bool
    {
        return $this->cancellation_requested_at !== null && $this->status === 'approved';
    }

    public function isAttendanceNotYet(): bool
    {
        return $this->attendance === 'not_yet';
    }

    public function isAttended(): bool
    {
        return $this->attendance === 'attended';
    }

    public function isNoshow(): bool
    {
        return $this->attendance === 'noshow';
    }
}
