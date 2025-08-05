<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IcSurveyorReport extends Model
{
    use HasFactory;

    protected $table = 'ic_surveyor_report';
    protected $primaryKey = 'ic_surveyor_report_id';
    protected $guarded = [];
    public $timestamps = false;
}
