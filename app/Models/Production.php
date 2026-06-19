<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Production extends Model
{
    use HasFactory;
    protected $table = 'productions';
    protected $fillable = [
        'production_no',
        'production_date',
        'item_id',
        'quantity',
        'total_cost',
        'is_deleted',
        'created_by',
        'exchange_rate',
        'status',
        'waste_quantity',
        'updated_by'
    ];

    public function productionDetails()
    {
        return $this->hasMany(ProductionDetail::class, 'production_id');
    }

    public function item()
    {
        return $this->belongsTo(Items::class, 'item_id');
    }
}
