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
        'item_image',
        'quantity',
        'price',
        'discount',
        'total_price',
        'scale',
    ];

    public function item()
    {
        return $this->belongsTo(Items::class, 'item_id', 'item_id');
    }

     public function quotation()
    {
        return $this->belongsTo(Quotation::class, 'quotation_id','quotation_id');
    }
}
