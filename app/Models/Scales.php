<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Scales extends Model
{
    use HasFactory;
    protected $primaryKey = 'scale_id';
    protected $fillable = ['scale_name','created_by'];

    // public function scales(){
    //     return $this->hasMany(Items::class);
    // }
}
