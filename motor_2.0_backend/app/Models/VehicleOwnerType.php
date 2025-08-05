<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VehicleOwnerType extends Model
{
    use HasFactory;

    protected $table = 'vehicle_owner_type';
    protected $primaryKey = 'owner_type_id';
    protected $guarded = [];
    public $timestamps = false;
}
