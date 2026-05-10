<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PtScheduleDay extends Model
{
    protected $fillable = [
        'pt_schedule_id',
        'day',
        'time',
    ];

    protected $casts = [
        'time' => 'datetime:H:i',
    ];

    public function ptSchedule(): BelongsTo
    {
        return $this->belongsTo(PtSchedule::class);
    }
}
