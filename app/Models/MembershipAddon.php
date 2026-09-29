<?php

namespace App\Models;

use Database\Factories\MembershipAddonFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MembershipAddon extends Model
{
    /** @use HasFactory<MembershipAddonFactory> */
    use HasFactory;

    protected $fillable = [
        'membership_id', 'name', 'duration_months', 'duration_weeks', 'duration_days',
        'start_date', 'end_date', 'status', 'approval_status', 'requested_by', 'requested_at',
        'decided_by', 'decided_at', 'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'duration_months' => 'integer', 'duration_weeks' => 'integer', 'duration_days' => 'integer',
            'start_date' => 'date', 'end_date' => 'date', 'requested_at' => 'datetime', 'decided_at' => 'datetime',
        ];
    }

    public function membership(): BelongsTo
    {
        return $this->belongsTo(Membership::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by')->withTrashed();
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by')->withTrashed();
    }

    public function durationInDays(): int
    {
        return $this->duration_months * 30 + $this->duration_weeks * 7 + $this->duration_days;
    }

    public function scopeAccessibleTo(Builder $query, User $user): Builder
    {
        return $query->whereHas('membership', function (Builder $membership) use ($user): void {
            $membership->where('user_id', $user->id)
                ->orWhereHas('members', fn (Builder $members) => $members->whereKey($user->id));
        });
    }

    public function scopeUsable(Builder $query): Builder
    {
        return $query->where('approval_status', 'approved')->where('status', 'active')
            ->whereDate('start_date', '<=', today('Asia/Jakarta'))
            ->whereDate('end_date', '>=', today('Asia/Jakarta'));
    }
}
