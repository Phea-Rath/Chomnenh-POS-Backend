<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockDetails extends Model
{
    protected $primaryKey = 'detail_id';
    protected $fillable = ['stock_id','item_id','exchange_rate', 'item_cost','quantity', 'expire_date','transection_date','is_wase','is_waste'];
}
