<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItems extends Model
{
    protected $fillable = ['order_id', 'item_id', 'item_price','discount','item_cost', 'price', 'quantity','item_for',  'is_delete'];
}
