<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShriramMotorTestMaster extends Model
{
    use HasFactory;

    protected $table = 'shriram_motor_test_master';
    protected $guarded = [];
    public $timestamps = false;
}
