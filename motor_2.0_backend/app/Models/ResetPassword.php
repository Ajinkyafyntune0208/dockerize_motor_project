<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ResetPassword extends Model
{
    use HasFactory;
    protected $table = 'reset_password';
    protected $fillable = [
        'user_id',
        'reset_link',
        'link_expire_at',
        'password_updated' 
    ];
}
