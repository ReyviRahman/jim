<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MembershipTransaction extends Model
{
    protected $fillable = [
        'invoice_number',
        'membership_id', 
        'user_id',
        'admin_id',
        'follow_up_id',
        'transaction_type',
        'package_name',
        'amount', 
        'payment_method',
        'payment_date',
        'start_date',
        'end_date',
        'notes',
    ];

    // Relasi ke User (Member yang bayar)
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // Relasi ke Admin/Kasir (Opsional, jika mau ditampilkan)
    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function membership()
    {
        return $this->belongsTo(Membership::class, 'membership_id');
    }
    
}
