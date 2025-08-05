<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MasterDistrict extends Model
{
    use HasFactory;

    protected $table = 'master_district';
    protected $primaryKey = 'district_id';
    protected $guarded = [];
    public $timestamps = false;
}
