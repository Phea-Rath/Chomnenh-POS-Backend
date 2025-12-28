<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExpanseMaster extends Model
{
    protected $primaryKey = 'expanse_id';
    protected $fillable = ['expanse_no', 'expanse_date', 'expanse_by', 'expanse_type', 'amount', 'created_by', 'expanse_other', 'expanse_supplier', 'is_active', 'is_deleted'];
}
