<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MasterStateDistCityMapIc extends Model
{
    use HasFactory;

    protected $table = 'master_state_dist_city_map_ic';
    protected $primaryKey = 'state_dist_city_map_ic_id';
    protected $guarded = [];
    public $timestamps = false;
}
