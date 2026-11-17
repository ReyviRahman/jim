<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PtBooking extends Model
{
    protected $fillable = [
        'studio_type',
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
        'is_free',
        'is_paid',
    ];

    protected $casts = [
        'booking_date' => 'date',
        'booking_time' => 'datetime',
        'cancelled_at' => 'datetime',
        'cancellation_requested_at' => 'datetime',
        'rejected_at' => 'datetime',
        'is_free' => 'boolean',
        'is_paid' => 'boolean',
    ];

    public const CLOSED_HOURS = [7, 12];

    /** @return list<string> */
    public static function bookableTimes(): array
    {
        return array_values(array_filter(self::hourlySlots(), fn (string $time): bool => ! in_array((int) $time, self::CLOSED_HOURS, true)));
    }

    /** @return list<string> */
    public static function hourlySlots(): array
    {
        return array_map(fn (int $hour): string => sprintf('%02d:00', $hour), range(7, 22));
    }

    public function studioLabel(): string
    {
        return match ($this->studio_type) {
            'private_studio' => 'Private Studio',
            'regular' => 'Regular',
            default => '—',
        };
    }

    public function membership(): BelongsTo
    {
        return $this->belongsTo(Membership::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(User::class, 'member_id')->withTrashed();
    }

    public function pt(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pt_id')->withTrashed();
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by')->withTrashed();
    }

    public function paymentBatchItem(): HasOne
    {
        return $this->hasOne(PtPaymentBatchItem::class);
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
