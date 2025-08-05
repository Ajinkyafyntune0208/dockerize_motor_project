<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MasterCarInsurance extends Model
{
    use HasFactory;

    protected $table = 'master_car_insurance';
    protected $primaryKey = 'car_insu_id';
    protected $guarded = [];
    public $timestamps = false;
}
