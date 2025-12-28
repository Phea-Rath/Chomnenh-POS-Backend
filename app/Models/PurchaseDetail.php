<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseDetail extends Model
{
    use HasFactory;
    protected $fillable = [
        'purchase_id',
        'item_id',
        'quantity',
        'item_cost',
        'attributes',
        'subtotal',
        'is_deleted'
    ];

    public $timestamps = false;


    public function item()
    {
        return $this->belongsTo(Items::class, 'item_id', 'item_id');
    }

    public function purchase()
    {
        return $this->belongsTo(Purchase::class, 'purchase_id', 'purchase_id');
    }
}
