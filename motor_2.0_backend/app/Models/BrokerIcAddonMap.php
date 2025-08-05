<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BrokerIcAddonMap extends Model
{
    use HasFactory;

    protected $table = 'broker_ic_addon_map';
    protected $primaryKey = 'broker_ic_addon_map_id';
    protected $guarded = [];
    public $timestamps = false;
}
