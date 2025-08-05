<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HdfcErgoV2BreakinLocationMaster extends Model
{
    use HasFactory;

    protected $guarded = [];
    protected $table = 'hdfc_ergo_v2_breakin_location_master';
    public $timestamps = FALSE;
}
