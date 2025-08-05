<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VehicleUses extends Model
{
    use HasFactory;

    protected $table = 'vehicle_uses';
    protected $primaryKey = 'vehicle_use_id';
    protected $guarded = [];
    public $timestamps = false;
}
