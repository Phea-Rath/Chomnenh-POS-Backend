<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Items;
use App\Models\User;

class Rating extends Model
{
    use HasFactory;
    protected $fillable = [
        'item_id',
        'user_id',
        'rating',
        'comment',
    ];

    // Relationship → Product
    public function item()
    {
        return $this->belongsTo(Items::class);
    }

    // Relationship → User
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
