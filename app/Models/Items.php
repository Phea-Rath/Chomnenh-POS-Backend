<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Items extends Model
{
    protected $primaryKey = "item_id";

    protected $fillable = ['item_code', 'barcode','discount', 'stock_in', 'reviews', 'rating', 'stock_out', 'wasted', 'sold', 'item_name', 'item_cost', 'item_price', 'wholesale_price', 'scale_id', 'category_id', "colors", 'item_type', 'brand_id', 'created_by', 'item_image'];
}
