<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExpanseItems extends Model
{
    protected $table = 'expense_items';
    protected $fillable = ['expense_id','expense_type_id','description','quantity','unit_price','sub_total'];
}
