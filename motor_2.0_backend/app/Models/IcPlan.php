<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IcPlan extends Model
{
    use HasFactory;

    protected $table = 'ic_plan';
    protected $primaryKey = 'ic_plan_id';
    protected $guarded = [];
    public $timestamps = false;
}
