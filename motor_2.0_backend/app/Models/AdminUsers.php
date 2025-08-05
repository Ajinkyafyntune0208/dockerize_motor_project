<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdminUsers extends Model
{
    use HasFactory;

    protected $table = 'admin_users';
    protected $primaryKey = 'user_id';
    protected $guarded = [];
    public $timestamps = false;
}
