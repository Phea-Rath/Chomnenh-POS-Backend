<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseStatus extends Model
{
    protected $table = 'purchase_status';
    protected $fillable = [
        'purchase_id',
        'status',
        'created_by'
    ];
}
