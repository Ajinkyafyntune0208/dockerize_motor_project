<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MotorTppd extends Model
{
    use HasFactory;

    protected $table = 'motor_tppd';
    protected $primaryKey = 'tppd_id';
    protected $guarded = [];
    public $timestamps = false;
}
