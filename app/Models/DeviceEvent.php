<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeviceEvent extends Model
{
    protected $attributes = [
        'is_found' => false,
    ];

    protected $fillable = [
        'device_code',
        'source_ip',
        'event_type',
        'employee_no',
        'is_found',
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

    protected function casts(): array
    {
        return [
            'is_found' => 'boolean',
        ];
    }
}
