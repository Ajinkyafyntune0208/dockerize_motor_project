<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MotorIdv extends Model
{
    use HasFactory;

    protected $table = 'motor_idv';
    protected $primaryKey = 'idv_id';
    protected $guarded = [];
    public $timestamps = false;
}
