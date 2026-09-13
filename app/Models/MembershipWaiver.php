<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MembershipWaiver extends Model
{
    use HasFactory;

    protected $fillable = [
        'membership_id', 'user_id', 'member_name', 'accepted', 'signature_path',
        'recorded_at', 'admin_id', 'terms_snapshot', 'consent_label',
    ];

    protected function casts(): array
    {
        return [
            'accepted' => 'boolean',
            'recorded_at' => 'datetime',
            'terms_snapshot' => 'array',
        ];
    }

    public function membership(): BelongsTo
    {
        return $this->belongsTo(Membership::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
