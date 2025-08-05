<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LsqJourneyIdMapping extends Model
{
    use HasFactory;
    protected $table = 'lsq_journey_id_mappings';
    protected $guarded = [];
}
