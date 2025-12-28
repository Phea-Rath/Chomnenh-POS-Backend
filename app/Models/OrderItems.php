<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItems extends Model
{
    protected $fillable = ['order_id', 'item_id', 'item_name', 'item_price','discount','item_cost','exchange_rate','item_wholesale_price', 'price', 'quantity', 'status', 'is_delete', 'is_cancelled'];
}
