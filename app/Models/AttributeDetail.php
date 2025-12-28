<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttributeDetail extends Model
{
    use HasFactory;
    protected $fillable = [
        'item_id',
        'attribute_id',
    ];
    // Relationship → Attribute
    public function attribute()
    {
        return $this->belongsTo(Attribute::class);
    }

    // Relationship → Product
    public function item()
    {
        return $this->belongsTo(Items::class);
    }
}
