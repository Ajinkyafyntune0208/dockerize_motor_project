<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MotorFeatures extends Model
{
    use HasFactory;

    protected $table = 'motor_features';
    protected $primaryKey = 'feature_id';
    protected $guarded = [];
    public $timestamps = false;
}
