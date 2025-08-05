<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MotorModel extends Model
{
    use HasFactory;

    protected $table = 'motor_model';
    protected $primaryKey = 'manf_id';
    protected $guarded = [];
    public $timestamps = false;
}
