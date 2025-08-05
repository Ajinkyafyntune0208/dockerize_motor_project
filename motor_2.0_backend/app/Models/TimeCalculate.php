<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TimeCalculate extends Model
{
    use HasFactory;

    protected $table = 'time_calculate';
    protected $primaryKey = 'time_calculate_id';
    protected $guarded = [];
    public $timestamps = false;
}
