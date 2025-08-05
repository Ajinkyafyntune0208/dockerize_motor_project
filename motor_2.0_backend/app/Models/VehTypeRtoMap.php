<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VehTypeRtoMap extends Model
{
    use HasFactory;

    protected $table = 'veh_type_rto_map';
    protected $primaryKey = 'veh_type_rto_map_id';
    protected $guarded = [];
    public $timestamps = false;
}
