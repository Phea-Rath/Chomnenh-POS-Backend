<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuotationDetail extends Model
{
    use HasFactory;
    protected $primaryKey = 'detail_id';

    public $timestamps = false;

    protected $fillable = [
        'quotation_id',
        'item_id',
        'item_name',
        'quantity',
        'price',
        'discount',
        'total_price',
        'scale',
    ];
}
