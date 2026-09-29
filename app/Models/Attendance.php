<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    protected $fillable = [
        'user_id',
        'nama_di_alat',
        'device_event_id',
        'membership_id',
        'membership_addon_id',
        'type',
        'attendance_status',
        'attendance_date',
        'check_in_time',
        'check_out_time',
    ];

    protected $casts = [
        'attendance_date' => 'date',
        'check_in_time' => 'datetime',
        'check_out_time' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function membership(): BelongsTo
    {
        return $this->belongsTo(Membership::class);
    }

    public function membershipAddon(): BelongsTo
    {
        return $this->belongsTo(MembershipAddon::class);
    }

    public function deviceEvent(): BelongsTo
    {
        return $this->belongsTo(DeviceEvent::class);
    }
}
