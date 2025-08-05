<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MotorMasterCover extends Model
{
    use HasFactory;

    protected $table = 'motor_master_cover';
    protected $primaryKey = 'motor_cover_id';
    protected $guarded = [];
    public $timestamps = false;
}
