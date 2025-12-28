<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttributeValueDetail extends Model
{
    use HasFactory;
    protected $fillable = [
        'attribute_detail_id',
        'attribute_value_id',
    ];

}
