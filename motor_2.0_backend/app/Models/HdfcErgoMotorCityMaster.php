<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HdfcErgoMotorCityMaster extends Model
{
    use HasFactory;
    protected $table = "hdfc_ergo_motor_city_master";

    public function state()
    {
        return $this->hasOne(HdfcErgoMotorStateMaster::class, 'num_state_cd', 'num_state_cd');
    }
}
