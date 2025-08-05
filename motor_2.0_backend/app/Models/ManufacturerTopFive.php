<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ManufacturerTopFive extends Model
{
    use HasFactory;
    protected $table = 'manufacturer_top_fives';
    protected $primaryKey = 'manufacturer_top_five_id';
}
