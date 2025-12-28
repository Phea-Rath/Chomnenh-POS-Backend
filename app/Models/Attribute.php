<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\AttributeValue;
use App\Models\Category;

class Attribute extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
        'type',
        'category_id',
        'created_by'
    ];

    // Relationship → Attribute values
    public function values()
    {
        return $this->hasMany(AttributeValue::class);
    }

    // Optional: attribute belongs to a category
    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}
