<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExpanseTypes extends Model
{
    protected $primaryKey = "expense_type_id";
    protected $table = "expense_types";
    protected $fillable = ["expense_type_name",'created_by'];
}
