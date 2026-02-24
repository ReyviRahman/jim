<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GymPackage extends Model
{
    protected $fillable = [
        'name',
        'price',
        'discount',
        'description',
        'is_active',
    ];

    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class); 
    }
}