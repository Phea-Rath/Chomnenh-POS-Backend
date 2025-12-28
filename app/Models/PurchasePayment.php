<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchasePayment extends Model
{
    use HasFactory;
    protected $fillable = [
        'purchase_id',
        'amount',
        'paid_at',
        'created_by',
        'is_deleted'
    ];

    public $timestamps = false;

    public function purchase()
    {
        return $this->belongsTo(Purchase::class, 'purchase_id', 'purchase_id');
    }
}
