<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MotorRiskDetails extends Model
{
    use HasFactory;

    protected $table = 'motor_risk_details';
    protected $primaryKey = 'motor_risk_id';
    protected $guarded = [];
    public $timestamps = false;
}
