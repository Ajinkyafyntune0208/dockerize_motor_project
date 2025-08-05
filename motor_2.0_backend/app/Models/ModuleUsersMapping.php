<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ModuleUsersMapping extends Model
{
    use HasFactory;

    protected $table = 'module_users_mapping';
    protected $primaryKey = 'module_mapping_id';
    protected $guarded = [];
    public $timestamps = false;
}
