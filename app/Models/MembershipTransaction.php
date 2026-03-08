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
        'transaction_type',
        'package_name',
        'amount', 
        'payment_method',
        'payment_date',
        'start_date',
        'end_date',
        'notes',
    ];
    
}
