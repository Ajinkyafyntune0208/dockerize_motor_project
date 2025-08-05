<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MisReport extends Model
{
    use HasFactory;

    protected $table = 'mis_report';
    protected $primaryKey = 'mis_report_id';
    protected $guarded = [];
    public $timestamps = false;
}
