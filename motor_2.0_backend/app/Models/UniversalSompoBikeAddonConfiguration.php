<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UniversalSompoBikeAddonConfiguration extends Model
{
    use HasFactory;
    protected $table = 'universal_sompo_bike_addon_configuration';
    protected $primaryKey = 'id';
    protected $guarded = [];
    public $timestamps = false;
}
