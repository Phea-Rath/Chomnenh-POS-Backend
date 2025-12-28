<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Customers extends Model
{
    use HasFactory;
    protected $primaryKey = 'customer_id';
    protected $fillable = [
        'customer_name',
        'customer_email',
        'customer_tel',
        'customer_address', 'communes', 'districts', 'provinces', 'villages', 'commune_id', 'district_id', 'province_id', 'village_id',
        'created_by', 'image',
        'is_deleted',
    ];

    public function orders()
    {
        return $this->hasMany(OrderMaster::class, 'order_customer_id', 'customer_id');
    }


}
