<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderMaster extends Model
{
    protected $primaryKey = 'order_id';
    protected $fillable = [
        'order_no',
        'order_customer_id',
        'through', 'sale_type',
        'order_tel',
        'order_address',
        'order_date',
        'delivery_fee',
        'deliver_id',
        'order_subtotal',
        'order_tax',
        'balance',
        'payment',
        'order_discount',
        'order_total',
        'created_by',
        'status',
        'order_payment_method',
        'order_payment_status',
        'is_active',
        'is_delete',
        'online',
        'exchange_rate',
        'reference_no',
        'due_date',
        'term'
    ];
}
