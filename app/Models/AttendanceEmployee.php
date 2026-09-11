<?php

namespace App\Models;

use App\EmployeeAttendanceStatus;
use Database\Factories\AttendanceEmployeeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceEmployee extends Model
{
    /** @use HasFactory<AttendanceEmployeeFactory> */
    use HasFactory;

    protected $table = 'attendance_employee';

    protected $attributes = ['status' => 'hadir'];

    protected $fillable = [
        'user_id', 'device_event_id', 'nama_di_alat', 'attendance_date',
        'check_in_time', 'check_out_time', 'shift_code', 'shift_name', 'shift_role',
        'shift_start_time', 'shift_end_time', 'scheduled_start_at', 'scheduled_end_at',
        'checkout_deadline_at', 'status', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => EmployeeAttendanceStatus::class,
            'attendance_date' => 'date',
            'check_in_time' => 'datetime',
            'check_out_time' => 'datetime',
            'scheduled_start_at' => 'datetime',
            'scheduled_end_at' => 'datetime',
            'checkout_deadline_at' => 'datetime',
        ];
    }

    public function isLate(): bool
    {
        return $this->status === EmployeeAttendanceStatus::Hadir
            && $this->check_in_time !== null
            && $this->scheduled_start_at !== null
            && $this->check_in_time->greaterThanOrEqualTo($this->scheduled_start_at->copy()->addMinutes(10));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function deviceEvent(): BelongsTo
    {
        return $this->belongsTo(DeviceEvent::class);
    }
}
