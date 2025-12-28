<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Roles extends Model
{
    use HasFactory;
    protected $table = 'roles';
    protected $primaryKey = 'role_id';
    protected $fillable = [
        'role_name',
        'role_description',
        'created_by',
        'is_deleted',
    ];

    public function users()
    {
        return $this->hasMany(Users::class, 'role_id', 'role_id');
    }

}
