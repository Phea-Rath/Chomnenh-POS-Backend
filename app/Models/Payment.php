<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $table = 'payments';
    protected $primaryKey = 'payment_id';
    protected $fillable = [
        'payment_method',
        'transection_id',
        'amount',
        'remark',
        'paid_at',
        'created_by'
    ];
}
