<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PolicyWiseRtoCluster extends Model
{
    use HasFactory;

    protected $table = 'policy_wise_rto_cluster';
    protected $primaryKey = 'policy_wise_rto_id';
    protected $guarded = [];
    public $timestamps = false;
}
