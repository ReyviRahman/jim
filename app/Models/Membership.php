<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Membership extends Model
{
    protected $fillable = [
        'user_id',
        'type', 
        'pt_id',
        'gym_package_id',
        'pt_package_id',
        'base_price',
        'discount_applied', 
        'price_paid',
        'total_sessions',
        'remaining_sessions',
        'member_goal',
        'start_date',
        'pt_end_date', 
        'membership_end_date', 
        'status',
    ];

    protected $casts = [
        'start_date' => 'date',
        'pt_end_date' => 'date', 
        'membership_end_date' => 'date', 
        'price_paid' => 'decimal:0',
        'total_sessions' => 'integer',
        'remaining_sessions' => 'integer',
    ];

    /**
     * Relasi ke Pembayar / Pendaftar Utama
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Relasi ke SEMUA Anggota yang berhak masuk dengan membership ini
     * (Untuk mengambil data istri/teman di paket Couple/Group)
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'membership_users', 'membership_id', 'user_id')
                    ->withTimestamps();
    }

    public function personalTrainer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pt_id');
    }

    public function gymPackage(): BelongsTo
    {
        return $this->belongsTo(GymPackage::class, 'gym_package_id');
    }

    public function ptPackage() {
        return $this->belongsTo(GymPackage::class, 'pt_package_id'); 
    }
}