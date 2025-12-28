<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExpanseTypes extends Model
{
    protected $primaryKey = "expanse_type_id";
    protected $fillable = ["expanse_type_name",'created_by'];
}
