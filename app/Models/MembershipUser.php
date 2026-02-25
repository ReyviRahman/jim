<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MembershipUser extends Model
{
    // Beritahu Laravel nama tabel spesifiknya
    protected $table = 'membership_users';

    protected $fillable = [
        'membership_id',
        'user_id',
    ];

    public function membership(): BelongsTo
    {
        return $this->belongsTo(Membership::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}