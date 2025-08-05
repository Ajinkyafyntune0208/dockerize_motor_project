<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AbiblDailerAttempt extends Model
{
    use HasFactory;

    protected $fillable = [
        "user_product_journey_id",
        "attempts",
        "next_attempts_on",
    ];
}
