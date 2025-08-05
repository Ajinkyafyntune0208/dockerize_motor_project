<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UniversalSompoCarAddonConfiguration extends Model
{
    use HasFactory;
    protected $table = 'universal_sompo_car_addon_configuration';
    protected $primaryKey = 'id';
    protected $guarded = [];
    public $timestamps = false;
}
