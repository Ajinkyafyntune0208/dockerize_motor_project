<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RoleMenuMapping extends Model
{
    use HasFactory;

    protected $table = 'role_menu_mapping';
    protected $primaryKey = 'map_id';
    protected $guarded = [];
    public $timestamps = false;
}
