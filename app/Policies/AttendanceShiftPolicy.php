<?php

namespace App\Policies;

use App\Models\AttendanceShift;
use App\Models\User;

class AttendanceShiftPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === 'admin';
    }

    public function create(User $user): bool
    {
        return $user->role === 'admin';
    }

    public function update(User $user, AttendanceShift $attendanceShift): bool
    {
        return $user->role === 'admin';
    }

    public function delete(User $user, AttendanceShift $attendanceShift): bool
    {
        return $user->role === 'admin';
    }
}
