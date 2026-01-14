<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Deliver extends Model
{
    use HasFactory;

    protected $table = 'delivers';
    protected $primaryKey = 'deliver_id';
    // public $timestamps = true;

    protected $fillable = [
        'deliver_name',
        'image',
        'created_by',
    ];

    // protected $casts = [
    //     'created_at' => 'datetime',
    //     'updated_at' => 'datetime',
    // ];

    /**
     * Get all orders associated with this deliver
     */
    public function orders()
    {
        return $this->hasMany(OrderMaster::class, 'deliver_id', 'deliver_id');
    }
}
