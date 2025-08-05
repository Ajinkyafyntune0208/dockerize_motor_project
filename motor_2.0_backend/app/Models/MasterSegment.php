<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MasterSegment extends Model
{
    use HasFactory;

    protected $table = 'master_segment';
    protected $primaryKey = 'segment_id';
    protected $guarded = [];
    public $timestamps = false;
}
