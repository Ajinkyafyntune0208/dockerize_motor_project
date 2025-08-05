<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SurveyorDocument extends Model
{
    use HasFactory;

    protected $table = 'surveyor_document';
    protected $primaryKey = 'surveyor_document_id';
    protected $guarded = [];
    public $timestamps = false;
}
