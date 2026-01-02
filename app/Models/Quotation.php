<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Quotation extends Model
{
    use HasFactory;
    protected $primaryKey = 'quotation_id';

    protected $fillable = [
        'quotation_number',
        'customer_id',
        'date',
        'credit_term',
        'date_term',
        'order_total',
        'delivery_fee',
        'total_discount',
        'grand_total',
        'status',
        'notes',
        'profile_id',
        'created_by',
    ];
}
