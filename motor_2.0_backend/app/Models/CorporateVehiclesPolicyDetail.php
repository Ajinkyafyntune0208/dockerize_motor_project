<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CorporateVehiclesPolicyDetail extends Model
{
    use HasFactory;

    protected $table = 'corporate_vehicles_policy_detail';
    protected $primaryKey = 'corporate_policy_detail_id';
    protected $guarded = [];
    public $timestamps = false;
}
