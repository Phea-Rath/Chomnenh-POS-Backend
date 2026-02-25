<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductionDetail extends Model
{
    use HasFactory;
    protected $table = 'production_details';
    protected $fillable = [
        'production_id',
        'raw_material_id',
        'quantity',
        'cost_per_unit',
        'total_cost',
        'is_deleted',
        'created_by',
    ];

    public function production()
    {
        return $this->belongsTo(Production::class, 'production_id');
    }
    public function items()
    {
        return $this->belongsTo(RawMaterial::class, 'raw_material_id');
    }
}
