<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExpanseItems extends Model
{
    protected $fillable = ['expanse_id','expanse_type_id','description','quantity','unit_price','sub_total'];
}
