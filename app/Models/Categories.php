<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Categories extends Model
{
    protected $primaryKey = 'category_id';
     protected $fillable = ['category_name','created_by'];

    // public function categorys(){
    //     return $this->hasMany(Items::class);
    // }
}
