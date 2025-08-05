<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MasterZoneOffice extends Model
{
    use HasFactory;

    protected $table = 'master_zone_office';
    protected $primaryKey = 'zone_office_id';
    protected $guarded = [];
    public $timestamps = false;
}
