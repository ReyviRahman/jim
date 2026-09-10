<?php

namespace App\Policies;

use App\Models\Shift;
use App\Models\User;

class ShiftPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === 'admin';
    }

    public function create(User $user): bool
    {
        return $user->role === 'admin';
    }

    public function update(User $user, Shift $shift): bool
    {
        return $user->role === 'admin';
    }

    public function delete(User $user, Shift $shift): bool
    {
        return $user->role === 'admin';
    }
}
