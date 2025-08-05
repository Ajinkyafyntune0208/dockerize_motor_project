<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MotorModelVersion extends Model
{
    use HasFactory;

    protected $table = 'motor_model_version';
    protected $primaryKey = 'version_id';
    protected $guarded = [];
    public $timestamps = false;
}
