<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    protected $fillable = [
        'user_id',
        'device_event_id',
        'membership_id',
        'type',
        'attendance_status',
        'check_in_time',
    ];

    protected $casts = [
        'check_in_time' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function membership(): BelongsTo
    {
        return $this->belongsTo(Membership::class);
    }

    public function deviceEvent(): BelongsTo
    {
        return $this->belongsTo(DeviceEvent::class);
    }
}
