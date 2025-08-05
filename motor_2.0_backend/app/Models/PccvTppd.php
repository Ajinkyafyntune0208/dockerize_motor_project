<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PccvTppd extends Model
{
    use HasFactory;

    protected $table = 'pccv_tppd';
    protected $primaryKey = 'tppd_id';
    protected $guarded = [];
    public $timestamps = false;
}
