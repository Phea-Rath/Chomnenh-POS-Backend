<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quotation extends Model
{
    use HasFactory;
    protected $primaryKey = 'quotation_id';

    protected $fillable = [
        'quotation_number',
        'customer_id',
        'date',
        'credit_term',
        'date_term',
        'order_total',
        'tax',
        'delivery_fee',
        'total_discount',
        'grand_total',
        'status',
        'notes',
        'profile_id',
        'created_by',
    ];
    public function customer()
    {
        return $this->belongsTo(Customers::class, 'customer_id', 'customer_id');
    }

    public function details(): HasMany
    {
        return $this->hasMany(QuotationDetail::class, 'quotation_id','quotation_id');
    }
}
