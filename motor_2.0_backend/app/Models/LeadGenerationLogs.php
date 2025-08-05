<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeadGenerationLogs extends Model
{

    protected $table = 'lead_generation_logs';
    protected $guarded = [];
    use HasFactory;
}
