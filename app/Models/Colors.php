<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Colors extends Model
{
    use HasFactory;
    protected $primaryKey = "color_id";
    protected $fillable = ['color_name','color_pick','created_by'];
}
