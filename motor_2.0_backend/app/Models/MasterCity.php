<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MasterCity extends Model
{
    use HasFactory;

    protected $table = 'master_city';
    protected $primaryKey = 'city_id';
    protected $guarded = [];
    public $timestamps = false;
}
