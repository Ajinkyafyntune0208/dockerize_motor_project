<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VehicleTypeRegistration extends Model
{
    use HasFactory;

    protected $table = 'vehicle_type_registration';
    protected $primaryKey = 'vehicle_type_id';
    protected $guarded = [];
    public $timestamps = false;
}
