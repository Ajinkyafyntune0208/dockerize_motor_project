<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MotorLpgcng extends Model
{
    use HasFactory;

    protected $table = 'motor_lpgcng';
    protected $primaryKey = 'lpgcng_id';
    protected $guarded = [];
    public $timestamps = false;
}
