<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockTypes extends Model
{
    protected $primaryKey = "stock_type_id";
    protected $fillable = ['stock_type_name','created_by'];
}
