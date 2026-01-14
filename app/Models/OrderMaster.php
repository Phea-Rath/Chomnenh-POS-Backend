<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderMaster extends Model
{
    protected $primaryKey = 'order_id';
    protected $fillable = ['order_no','order_customer_id','sale_type', 'order_tel', 'order_address', 'order_date', 'delivery_fee', 'delivery_by', 'order_subtotal', 'order_tax', 'balance', 'payment', 'order_discount', 'order_total', 'created_by', 'order_type', 'status', 'order_payment_method', 'order_payment_status', 'is_active', 'is_delete', 'online','is_cancelled'];
}
