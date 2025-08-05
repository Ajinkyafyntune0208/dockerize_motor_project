<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConfigureReport extends Model
{
    use HasFactory;

    protected $table = 'configure_report';
    protected $primaryKey = 'configure_report_id';
    protected $guarded = [];
    public $timestamps = false;
}
