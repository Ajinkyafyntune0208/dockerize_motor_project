<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserProductJourneyIdv extends Model
{
    use HasFactory;

    protected $table = 'user_product_journey_idv';
    protected $primaryKey = 'user_product_journey_idv_id';
    protected $guarded = [];
    public $timestamps = false;
}
