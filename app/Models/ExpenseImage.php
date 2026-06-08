<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExpenseImage extends Model
{
    protected $fillable = [
        'image_id',
        'expense_id'
    ];
}
