<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PccvIcMaster extends Model
{
    use HasFactory;

    protected $table = 'pccv_ic_master';
    protected $guarded = [];
    public $timestamps = false;
}
