<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transfers extends Model
{
    protected $fillable = ['from_warehouses','to_warehouses','id_transfer'];
}
