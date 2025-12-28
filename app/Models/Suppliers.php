<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Suppliers extends Model
{
    use HasFactory;
    protected $primaryKey = 'supplier_id';
    protected $fillable = ['supplier_name', 'supplier_address', 'communes', 'districts', 'provinces', 'villages', 'commune_id', 'district_id', 'province_id', 'village_id', 'supplier_tel', 'supplier_email', 'created_by', 'image', 'is_deleted'];
}
