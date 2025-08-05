<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserRoles extends Model
{
    use HasFactory;

    protected $table = 'user_roles';
    protected $primaryKey = 'role_id';
    protected $guarded = [];
    public $timestamps = false;
}
