<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BrokerIcMap extends Model
{
    use HasFactory;

    protected $table = 'broker_ic_map';
    protected $primaryKey = 'broker_ic_map_id';
    protected $guarded = [];
    public $timestamps = false;
}
