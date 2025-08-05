<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BikeManufacturer extends Model
{
    use HasFactory;

    protected $table = 'bike_manufacturer';
    protected $primaryKey = 'manf_id';
    protected $guarded = [];
    public $timestamps = false;
}
