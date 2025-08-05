<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VehicleFixedValueCoverRates extends Model
{
    use HasFactory;

    protected $table = 'vehicle_fixed_value_cover_rates';
    protected $primaryKey = 'rate_id';
    protected $guarded = [];
    public $timestamps = false;
}
