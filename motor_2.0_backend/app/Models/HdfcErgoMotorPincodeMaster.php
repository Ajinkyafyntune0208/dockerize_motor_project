<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HdfcErgoMotorPincodeMaster extends Model
{
    use HasFactory;
    protected $table = "hdfc_ergo_motor_pincode_master";

    /**
     * Get the state associated with the pincode.
     */
    public function state()
    {
        return $this->hasOne(HdfcErgoMotorStateMaster::class,'num_state_cd','num_state_cd');
    }

    /**
     * Get the cities for the pincodes.
     */
    public function city()
    {
        return $this->hasOne(HdfcErgoMotorCityMaster::class,'num_citydistrict_cd','num_citydistrict_cd');
    }
}
