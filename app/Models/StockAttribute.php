<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockAttribute extends Model
{
    use HasFactory;
    protected $fillable = ['stock_detail_id', 'item_id', 'attribute_id', 'attribute_value_id'];
}
