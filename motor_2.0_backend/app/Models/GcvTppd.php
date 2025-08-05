<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GcvTppd extends Model
{
    use HasFactory;

    protected $table = 'gcv_tppd';
    protected $primaryKey = 'tppd_id';
    protected $guarded = [];
    public $timestamps = false;
}
