<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PolicyWiseSubSegment extends Model
{
    use HasFactory;

    protected $table = 'policy_wise_sub_segment';
    protected $primaryKey = 'sub_segment_id';
    protected $guarded = [];
    public $timestamps = false;
}
