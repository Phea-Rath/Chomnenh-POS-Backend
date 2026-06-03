<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sipping extends Model
{
    protected $table = 'shipping';

    protected $fillable = [
        'purchase_id',
        'tracking_number',
        'carrier',
        'fee',
        'vai',
        'remark',
        'term',
        'date',
        'created_by'
    ];

    public function purchase()
    {
        return $this->belongsTo(Purchase::class, 'purchase_id', 'purchase_id');
    }
}
