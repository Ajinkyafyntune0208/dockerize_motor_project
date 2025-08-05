<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserLoginToken extends Model
{
    use HasFactory;

    protected $table = 'user_login_token';
    protected $primaryKey = 'ult_id';
    protected $guarded = [];
    public $timestamps = false;
}
