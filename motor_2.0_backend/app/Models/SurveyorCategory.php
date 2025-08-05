<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SurveyorCategory extends Model
{
    use HasFactory;

    protected $table = 'surveyor_category';
    protected $primaryKey = 'surveyor_category_id';
    protected $guarded = [];
    public $timestamps = false;
}
