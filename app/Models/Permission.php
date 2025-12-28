<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    use HasFactory;
    protected $table = "permission";
    public $incrementing = false;
    public $timestamps = false;
    protected $fillable = ['user_id', 'menu_id'];
}
