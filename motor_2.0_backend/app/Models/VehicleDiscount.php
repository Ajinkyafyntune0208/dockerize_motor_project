<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VehicleDiscount extends Model
{
    use HasFactory;

    protected $table = 'vehicle_discount';
    protected $primaryKey = 'discount_id';
    protected $guarded = [];
    public $timestamps = false;
}
