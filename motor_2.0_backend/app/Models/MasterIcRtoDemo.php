<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MasterIcRtoDemo extends Model
{
    use HasFactory;

    protected $table = 'master_ic_rto_demo';
    protected $primaryKey = 'range_id';
    protected $guarded = [];
    public $timestamps = false;
}
