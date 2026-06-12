<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Purchase extends Model
{
    use HasFactory;

    protected $primaryKey = 'purchase_id';

    protected $fillable = [
        'purchase_no',
        'supplier_id',
        'purchase_date',
        'sub_total',
        'tax_rate',
        'tax_amount',
        'shipping_fee',
        'total_amount',
        'total_discount',
        'total_paid',
        'balance',
        'quote_no',
        'purchase_type',
        'created_by',
        'seller',
        'approved_by',
        'updated_by',
        'description',
        'status',
        'updated_by',
        'is_deleted'
    ];

    public function details()
    {
        return $this->hasMany(PurchaseDetail::class, 'purchase_id', 'purchase_id');
    }

    public function payments()
    {
        return $this->hasMany(PurchasePayment::class, 'purchase_id', 'purchase_id');
    }

    public function supplier()
    {
        return $this->belongsTo(Suppliers::class, 'supplier_id', 'supplier_id');
    }
    public function users()
    {
        return $this->belongsTo(Users::class, 'created_by', 'id');
    }
}
