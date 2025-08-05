<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MotorManufacturer extends Model
{
    use HasFactory;

    protected $table = 'motor_manufacturer';
    protected $primaryKey = 'manf_id';
    protected $guarded = [];
    public $timestamps = false;
}
