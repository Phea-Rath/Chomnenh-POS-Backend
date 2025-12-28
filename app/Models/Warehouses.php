<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Warehouses extends Model
{
    protected $primaryKey = "warehouse_id";
    protected $fillable = ['warehouse_name','status','created_by'];

    // public function werehouses(){
    //     return $this->hasMany(Items::class);
    // }
}
