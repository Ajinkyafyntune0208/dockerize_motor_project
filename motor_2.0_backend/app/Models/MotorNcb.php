<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MotorNcb extends Model
{
    use HasFactory;

    protected $table = 'motor_ncb';
    protected $primaryKey = 'ncb_id';
    protected $guarded = [];
    public $timestamps = false;
}
