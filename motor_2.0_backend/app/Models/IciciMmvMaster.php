<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IciciMmvMaster extends Model
{
    use HasFactory;

    protected $table = 'icici_mmv_master';
    protected $primaryKey = 'manf_code';
    protected $guarded = [];
    public $timestamps = false;
}
