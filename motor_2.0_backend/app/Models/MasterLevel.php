<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MasterLevel extends Model
{
    use HasFactory;

    protected $table = 'master_level';
    protected $primaryKey = 'level_id';
    protected $guarded = [];
    public $timestamps = false;
}
