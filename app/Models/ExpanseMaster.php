<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExpanseMaster extends Model
{
    protected $primaryKey = 'expense_id';
    protected $table = 'expense_masters';
    protected $fillable = ['expense_no', 'expense_date', 'expense_by', 'purchased_by', 'expense_type', 'amount', 'created_by', 'expense_other', 'expense_supplier', 'is_active', 'is_deleted'];
}
