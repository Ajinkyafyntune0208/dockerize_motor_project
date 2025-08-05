<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RestrictedVehicleNo extends Model
{
    use HasFactory;

    protected $fillable = ['vehicle_registration_number', 'status'];
}
