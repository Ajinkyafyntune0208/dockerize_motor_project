<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SegmentVersionMap extends Model
{
    use HasFactory;

    protected $table = 'segment_version_map';
    protected $primaryKey = 'segment_version_map_id';
    protected $guarded = [];
    public $timestamps = false;
}
