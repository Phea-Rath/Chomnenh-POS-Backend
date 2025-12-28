<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Attribute;
use App\Models\Items;

class AttributeValue extends Model
{
    use HasFactory;
    protected $fillable = [
        'value',
    ];


}
