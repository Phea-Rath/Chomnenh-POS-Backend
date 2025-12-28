<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Menus extends Model
{
    use HasFactory;
    protected $table = 'menus';
    protected $primaryKey = "menu_id";
    protected $fillable = ['menu_name', 'menu_type', 'menu_icon', 'menu_path'];
}
