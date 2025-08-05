<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AbiblDropoutJourney extends Model
{
    use HasFactory;

    protected $fillable = [ 'user_product_journey_id', 'status'];
}
