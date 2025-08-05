<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MotorAddon extends Model
{
    use HasFactory;

    protected $table = 'motor_addon';
    protected $primaryKey = 'addon_id';
    protected $guarded = [];
    public $timestamps = false;
}
