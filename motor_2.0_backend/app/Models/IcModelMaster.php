<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IcModelMaster extends Model
{
    use HasFactory;

    protected $table = 'ic_model_master';
    protected $primaryKey = 'ic_model_master_id';
    protected $guarded = [];
    public $timestamps = false;
}
