<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CarApplicableAddon extends Model
{
    use HasFactory;
    protected $table = 'car_applicable_addons';
    protected $primaryKey = 'addon_id';
    protected $guarded = [];
    public $timestamps = false;
}
