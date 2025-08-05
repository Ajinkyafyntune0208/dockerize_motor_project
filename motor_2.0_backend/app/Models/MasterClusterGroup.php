<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MasterClusterGroup extends Model
{
    use HasFactory;

    protected $table = 'master_cluster_group';
    protected $primaryKey = 'cluster_group_id';
    protected $guarded = [];
    public $timestamps = false;
}
