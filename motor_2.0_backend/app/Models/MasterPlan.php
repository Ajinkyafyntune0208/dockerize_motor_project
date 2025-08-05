<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MasterPlan extends Model
{
    use HasFactory;

    protected $table = 'master_plan';
    protected $primaryKey = 'plan_id';
    protected $guarded = [];
    public $timestamps = false;
}
