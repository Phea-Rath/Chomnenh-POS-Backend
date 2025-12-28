<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExchangeRate extends Model
{
    use HasFactory;
    protected $table = 'exchange_rate';
    protected $primaryKey = 'profile_id';
    protected $fillable = ['usd_to_khr', 'profile_id'];
}
