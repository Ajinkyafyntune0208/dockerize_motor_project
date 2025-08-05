<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IciciRtoLocationMaster extends Model
{
    use HasFactory;

    protected $table = 'icici_lombard_rto_location_motor';
    protected $guarded = [];
    public $timestamps = false;
}
