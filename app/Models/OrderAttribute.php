<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderAttribute extends Model
{
    use HasFactory;
    protected $fillable = ['order_item_id', 'item_id', 'attribute_id', 'attribute_value_id'];
}
