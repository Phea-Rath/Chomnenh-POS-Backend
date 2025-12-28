<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Brands extends Model
{
    protected $primaryKey = "brand_id";
    protected $fillable = ['brand_name','created_by'];

    // public function brands(){
    //     return $this->hasMany(Items::class);
    // }
}
