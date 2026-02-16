<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Profile extends Model
{
    use HasFactory;
    protected $fillable = ["profile_name","telephone","bot_token","chat_id","qr_code","start_date", 'address', "term","end_date","created_by","image"];
}
