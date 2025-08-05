<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TemplateMaster extends Model
{
    use HasFactory;

    protected $table = 'template_master';
    protected $primaryKey = 'temp_id';
    protected $guarded = [];
    public $timestamps = false;
}
