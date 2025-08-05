<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MasterZone extends Model
{
    use HasFactory;

    protected $table = 'master_zone';
    protected $primaryKey = 'zone_id';
    protected $guarded = [];
    public $timestamps = false;
}
