<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CorporateVehiclesDetails extends Model
{
    use HasFactory;

    protected $table = 'corporate_vehicles_details';
    protected $primaryKey = 'corporate_vihicle_detail_id';
    protected $guarded = [];
    public $timestamps = false;
}
