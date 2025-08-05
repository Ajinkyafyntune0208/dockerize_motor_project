<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PremiumCalculate extends Model
{
    use HasFactory;

    protected $table = 'premium_calculate';
    protected $primaryKey = 'premium_calculate_id';
    protected $guarded = [];
    public $timestamps = false;
}
