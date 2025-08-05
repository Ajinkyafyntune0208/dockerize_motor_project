<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FastlaneVehDescripModel extends Model
{
    use HasFactory;
    protected $table = 'fastlane_vehicle_description';
    public $timestamps = false;
    protected $fillable = [
        'section',
        'description'
    ];
}
