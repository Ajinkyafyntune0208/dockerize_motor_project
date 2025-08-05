<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MotorTariff extends Model
{
    use HasFactory;

    protected $table = 'motor_tariff';
    protected $primaryKey = 'tariff_id';
    protected $guarded = [];
    public $timestamps = false;
}
