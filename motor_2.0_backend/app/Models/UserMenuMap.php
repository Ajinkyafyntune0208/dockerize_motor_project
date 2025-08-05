<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserMenuMap extends Model
{
    use HasFactory;

    protected $table = 'user_menu_map';
    protected $primaryKey = 'user_menu_map_id';
    protected $guarded = [];
    public $timestamps = false;
}
