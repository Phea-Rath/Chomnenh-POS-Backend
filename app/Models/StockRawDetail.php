<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockRawDetail extends Model
{
    use HasFactory;

    protected $table = 'stock_raw_details';

    protected $fillable = [
        'stock_id',
        'raw_material_id',
        'item_cost',
        'quantity',
        'expire_date',
        'transection_date',
        'is_waste',
        'is_deleted',
    ];

    protected $casts = [
        'expire_date' => 'date',
        'transection_date' => 'datetime',
        'is_waste' => 'boolean',
        'is_deleted' => 'boolean',
    ];
}
