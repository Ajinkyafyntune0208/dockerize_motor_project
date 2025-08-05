<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GcvMotorTarif extends Model
{
    use HasFactory;

    protected $table = 'gcv_motor_tarif';
    protected $primaryKey = 'tattif_id';
    protected $guarded = [];
    public $timestamps = false;
}
