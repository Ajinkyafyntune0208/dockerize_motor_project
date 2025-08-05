<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LsqServiceLogs extends Model
{
    use HasFactory;
    protected $table = 'lsq_service_logs';
    protected $guarded = [];
}
