<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MasterSegmentGroup extends Model
{
    use HasFactory;

    protected $table = 'master_segment_group';
    protected $primaryKey = 'segment_group_id';
    protected $guarded = [];
    public $timestamps = false;
}
