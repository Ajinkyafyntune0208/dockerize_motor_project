<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RehitRestrictEnq extends Model
{
    use HasFactory;

    protected $fillable = ['user_product_journey_id', 'attempts'];
    public $timestamps = false;
}
