<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeviceEvent extends Model
{
    protected $fillable = [
        'device_code',
        'source_ip',
        'event_type',
        'employee_no',
        'name',
        'card_no',
        'door_no',
        'swipe_result',
        'attendance_status',
        'verify_mode',
        'accessed_at',
        'payload',
        'status',
        'error_message',
    ];
}
