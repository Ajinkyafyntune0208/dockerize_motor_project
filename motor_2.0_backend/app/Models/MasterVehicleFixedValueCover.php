<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MasterVehicleFixedValueCover extends Model
{
    use HasFactory;

    protected $table = 'master_vehicle_fixed_value_cover';
    protected $primaryKey = 'cover_id';
    protected $guarded = [];
    public $timestamps = false;
}
