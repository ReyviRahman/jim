<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MembershipTransaction extends Model
{
    protected $fillable = [
        'operational_request_id',
        'invoice_number',
        'membership_id',
        'membership_hold_id',
        'user_id',
        'admin_id',
        'shift',
        'follow_up_id',
        'follow_up_id_two',
        'transaction_type',
        'package_name',
        'amount',
        'payment_method',
        'payment_proof_path',
        'payment_date',
        'start_date',
        'end_date',
        'notes',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'start_date' => 'date',
        'end_date' => 'date',
        'amount' => 'decimal:0',
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

    public function followUp()
    {
        return $this->belongsTo(User::class, 'follow_up_id');
    }

    public function followUpTwo()
    {
        return $this->belongsTo(User::class, 'follow_up_id_two');
    }

    public function membership()
    {
        return $this->belongsTo(Membership::class, 'membership_id');
    }

    public function hold(): BelongsTo
    {
        return $this->belongsTo(MembershipHold::class, 'membership_hold_id');
    }

    public function operationalRequest(): BelongsTo
    {
        return $this->belongsTo(MembershipOperationalRequest::class, 'operational_request_id');
    }

    public function isOperational(): bool
    {
        return $this->payment_method === 'operasional';
    }
}
