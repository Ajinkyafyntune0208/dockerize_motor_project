<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FgCarAddonConfiguration extends Model
{
    use HasFactory;
    protected $table = 'fg_car_addon_configuration';
    protected $primaryKey = 'id';
    protected $guarded = [];
    public $timestamps = false;
}
