<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TataAigModelMaster extends Model
{
    use HasFactory;

    protected $table = 'tata_aig_model_master';
    protected $guarded = [];
    public $timestamps = false;
}
