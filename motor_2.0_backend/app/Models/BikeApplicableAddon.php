<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BikeApplicableAddon extends Model
{
    use HasFactory;
    protected $table = 'bike_applicable_addons';
    protected $primaryKey = 'addon_id';
    protected $guarded = [];
    public $timestamps = false;
}
