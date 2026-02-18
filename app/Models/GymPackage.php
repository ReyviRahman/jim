<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GymPackage extends Model
{
    protected $fillable = [
        'name',
        'price',
        'number_of_sessions',
        'description',
        'is_active',
    ];
}