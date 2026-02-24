<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Membership extends Model
{
  protected $fillable = [
    'user_id',
    'pt_id',
    'gym_package_id',
    'base_price',
    'discount_applied', 
    'price_paid',
    'total_sessions',
    'remaining_sessions',
    'member_goal',
    'start_date',
    'end_date',
    'status',
  ];

  protected $casts = [
    'start_date' => 'date',
    'end_date' => 'date',
    'price_paid' => 'decimal:0',
    'total_sessions' => 'integer',
    'remaining_sessions' => 'integer',
  ];

  public function user(): BelongsTo
  {
    return $this->belongsTo(User::class, 'user_id');
  }

  public function personalTrainer(): BelongsTo
  {
    return $this->belongsTo(User::class, 'pt_id');
  }

  public function gymPackage(): BelongsTo
  {
    return $this->belongsTo(GymPackage::class, 'gym_package_id');
  }
}
