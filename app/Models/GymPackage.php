<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GymPackage extends Model
{
    protected $fillable = [
        'type',
        'name',
        'category',
        'max_members',
        'pt_sessions',
        'price',
        'discount',
        'is_active',
    ];

    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class); 
    }
}