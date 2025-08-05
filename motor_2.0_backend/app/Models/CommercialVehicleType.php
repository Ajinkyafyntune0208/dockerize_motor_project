<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommercialVehicleType extends Model
{
    use HasFactory;

    protected $table = 'commercial_vehicle_type';
    protected $primaryKey = 'com_vehicle_type_id';
    protected $guarded = [];
    public $timestamps = false;
}
