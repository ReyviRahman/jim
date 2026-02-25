<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name', 'occupation', 'age', 'gender', 'phone',
        'medical_history', 'email', 'password', 'joined_at',
        'address', 'is_active', 'photo', 'role',
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'joined_at' => 'date',
            'password' => 'hashed',
        ];
    }

    // --- RELASI BARU ---

    /**
     * Relasi sebagai Anggota yang memiliki AKSES ke Gym/PT
     * Menggunakan tabel pivot membership_users
     */
    public function memberships(): BelongsToMany
    {
        return $this->belongsToMany(Membership::class, 'membership_users', 'user_id', 'membership_id')
                    ->withTimestamps();
    }

    /**
     * Relasi sebagai Pendaftar/Pembayar Utama dari sebuah transaksi
     */
    public function paidMemberships(): HasMany
    {
        return $this->hasMany(Membership::class, 'user_id');
    }

    // --- UPDATE METHOD ---
    
    public function activeMembership()
    {
        // Sekarang mengecek akses dari pivot table, bukan cuma yang dia beli
        return $this->memberships()
            ->where('status', 'active')
            ->first();
    }

    public function pendingMembership()
    {
        return $this->memberships()
            ->where('status', 'pending')
            ->first();
    }
}