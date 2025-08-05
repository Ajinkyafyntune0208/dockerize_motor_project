<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SurveyorClaimMapping extends Model
{
    use HasFactory;

    protected $table = 'surveyor_claim_mapping';
    protected $primaryKey = 'surveyor_claim_mapping_id';
    protected $guarded = [];
    public $timestamps = false;
}
