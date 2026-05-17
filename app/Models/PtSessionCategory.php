<?php

namespace App\Models;

use Database\Factories\PtSessionCategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PtSessionCategory extends Model
{
    /** @use HasFactory<PtSessionCategoryFactory> */
    use HasFactory;

    protected $fillable = [
        'pt_id',
        'category',
        'amount',
        'description',
    ];

    protected $casts = [
        'amount' => 'decimal:0',
    ];

    public function pt(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pt_id');
    }
}
