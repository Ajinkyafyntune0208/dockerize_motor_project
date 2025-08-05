<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NicVehicleColorMaster extends Model
{
    use HasFactory;
    protected $table = 'nic_vehicle_color_master';
    protected $guarded = [];
    public $timestamps = false;
}
