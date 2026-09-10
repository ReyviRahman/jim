<?php

namespace App\Models;

use Database\Factories\AttendanceShiftFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttendanceShift extends Model
{
    /** @use HasFactory<AttendanceShiftFactory> */
    use HasFactory;

    protected $fillable = ['code', 'name', 'start_time', 'end_time'];
}
