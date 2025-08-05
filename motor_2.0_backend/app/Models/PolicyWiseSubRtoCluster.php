<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PolicyWiseSubRtoCluster extends Model
{
    use HasFactory;

    protected $table = 'policy_wise_sub_rto_cluster';
    protected $primaryKey = 'sub_cluster_id';
    protected $guarded = [];
    public $timestamps = false;
}
