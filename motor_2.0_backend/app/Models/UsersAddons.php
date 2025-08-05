<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UsersAddons extends Model
{
    use HasFactory;

    protected $table = 'users_addons';
    protected $guarded = [];
    public $timestamps = false;
}
