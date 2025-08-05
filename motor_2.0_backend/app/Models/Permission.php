<?php

namespace App\Models;

use Spatie\Permission\Models\Permission as SpatiePermission;

class Permission extends SpatiePermission
{
    protected $table = 'permissions';
    protected $primaryKey = 'id';
    protected $guarded = [];
    protected $fillable = ['name','guard_name','created_at','updated_at'];
}
