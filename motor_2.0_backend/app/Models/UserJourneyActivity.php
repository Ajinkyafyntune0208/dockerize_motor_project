<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserJourneyActivity extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_product_journey_id', 'st_token', 'ls_token'
    ];
}
