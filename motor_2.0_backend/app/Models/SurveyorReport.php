<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SurveyorReport extends Model
{
    use HasFactory;

    protected $table = 'surveyor_report';
    protected $primaryKey = 'surveyor_report_id';
    protected $guarded = [];
    public $timestamps = false;
}
