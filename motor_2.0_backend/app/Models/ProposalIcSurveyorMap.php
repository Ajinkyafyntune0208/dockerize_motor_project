<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProposalIcSurveyorMap extends Model
{
    use HasFactory;

    protected $table = 'proposal_ic_surveyor_map';
    protected $primaryKey = 'proposal_ic_surveyor_map_id';
    protected $guarded = [];
    public $timestamps = false;
}
