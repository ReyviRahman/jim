<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeviceEvent extends Model
{
    protected $fillable = [
        'device_code',
        'source_ip',
        'event_type',
        'payload',
        'status',
        'error_message',
    ];
}
