<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MasterPermission extends Model
{
    use HasFactory;

    protected $table = 'master_permission';
    protected $guarded = [];
    public $timestamps = false;
}
