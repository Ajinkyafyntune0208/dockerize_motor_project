<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeadStageMaster extends Model
{
    use HasFactory;

    protected $table = 'lead_stage_master';
    protected $primaryKey = 'lead_stage_id';
    protected $guarded = [];
    public $timestamps = false;
}
