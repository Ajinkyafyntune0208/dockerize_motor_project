<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MisUserCustomReport extends Model
{
    use HasFactory;

    protected $table = 'mis_user_custom_report';
    protected $primaryKey = 'mis_user_custom_report_id';
    protected $guarded = [];
    public $timestamps = false;
}
