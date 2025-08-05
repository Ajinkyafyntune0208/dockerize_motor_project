<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VehicleCcwiseSegment extends Model
{
    use HasFactory;

    protected $table = 'vehicle_ccwise_segment';
    protected $guarded = [];
    public $timestamps = false;
}
