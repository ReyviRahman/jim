<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Period extends Model
{
    protected $fillable = ['name'];

    public function ptSessions(): HasMany
    {
        return $this->hasMany(PtSession::class);
    }
}
