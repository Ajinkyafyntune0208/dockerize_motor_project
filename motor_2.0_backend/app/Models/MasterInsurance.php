<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MasterInsurance extends Model
{
    use HasFactory;

    protected $table = 'master_insurance';
    protected $primaryKey = 'insu_id';
    protected $guarded = [];
    public $timestamps = false;
}
