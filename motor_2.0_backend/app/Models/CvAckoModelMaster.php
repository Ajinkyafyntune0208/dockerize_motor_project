<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CvAckoModelMaster extends Model
{
    use HasFactory;

    protected $table = 'cv_acko_model_master';
    protected $guarded = [];
    public $timestamps = false;
}
