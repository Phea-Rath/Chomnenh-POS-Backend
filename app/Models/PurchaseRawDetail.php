<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseRawDetail extends Model
{
    use HasFactory;

    protected $table = 'purchase_raw_details';

    protected $fillable = [
        'purchase_id',
        'raw_material_id',
        'item_cost',
        'quantity',
        'unit_price',
        'subtotal',
        'is_deleted',
    ];

    protected $casts = [
        'item_cost' => 'decimal:4',
        'quantity' => 'decimal:4',
        'unit_price' => 'decimal:4',
        'subtotal' => 'decimal:4',
        'is_deleted' => 'boolean',
    ];
}
