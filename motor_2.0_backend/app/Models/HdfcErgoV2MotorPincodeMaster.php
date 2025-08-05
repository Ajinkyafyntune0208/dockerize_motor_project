<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HdfcErgoV2MotorPincodeMaster extends Model
{
    use HasFactory;

    protected $table = 'hdfc_ergo_v2_motor_pincode_master';

    public function state()
    {
        return $this->hasOne(HdfcErgoV2MotorStateMaster::class, 'value', 'state_id');
    }

    public function city()
    {
        return $this->hasOne(HdfcErgoV2MotorCityMaster::class, 'city_id', 'city_id');
    }
}
