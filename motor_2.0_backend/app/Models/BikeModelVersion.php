<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BikeModelVersion extends Model
{
    use HasFactory;

    protected $table = 'bike_model_version';
    protected $primaryKey = 'version_id';
    protected $guarded = [];
    public $timestamps = false;
}
