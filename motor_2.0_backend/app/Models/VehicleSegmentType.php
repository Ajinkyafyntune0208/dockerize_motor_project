<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VehicleSegmentType extends Model
{
    use HasFactory;

    protected $table = 'vehicle_segment_type';
    protected $primaryKey = 'segment_type_id';
    protected $guarded = [];
    public $timestamps = false;
}
