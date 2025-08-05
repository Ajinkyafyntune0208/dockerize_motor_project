<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DigitGcvMaster extends Model
{
    use HasFactory;

    protected $table = 'digit_gcv_master';
    protected $primaryKey = 'digit_gcv_id';
    protected $guarded = [];
    public $timestamps = false;
}
