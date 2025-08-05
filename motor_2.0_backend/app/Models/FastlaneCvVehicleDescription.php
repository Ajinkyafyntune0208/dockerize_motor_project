<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FastlaneCvVehicleDescription extends Model
{
    use HasFactory;
    protected $table = 'fastlane_cv_vehicle_description';
    public $timestamps = true;
    protected $fillable = [
        'cv_section',
        'vehicle_class',
        'vehicle_category',
    ];
}
