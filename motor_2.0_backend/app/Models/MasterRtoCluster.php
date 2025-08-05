<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MasterRtoCluster extends Model
{
    use HasFactory;

    protected $table = 'master_rto_cluster';
    protected $primaryKey = 'rto_group_id';
    protected $guarded = [];
    public $timestamps = false;
}
