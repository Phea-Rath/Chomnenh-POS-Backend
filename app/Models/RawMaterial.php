<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RawMaterial extends Model
{
    use HasFactory;

    protected $table = 'raw_materials';

    protected $fillable = [
        'created_by',
        'material_name',
        'material_code',
        'material_image',
        'primary_unit',
        'secondary_unit',
        'conversion_value',
    ];
}
