<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Pivot;

class ItemImage extends Pivot
{
    use HasFactory;
    protected $table = 'item_images';

    protected $fillable = [
        'item_id',
        'image_id',
    ];

    public function images()
    {
        return $this->belongsToMany(Image::class, 'images', 'item_id', 'image_id')
                    ->using(TableItemImage::class);
    }
    public function items()
    {
        return $this->belongsToMany(Item::class, 'item_images', 'image_id', 'item_id')
                    ->using(TableItemImage::class);
    }
}
