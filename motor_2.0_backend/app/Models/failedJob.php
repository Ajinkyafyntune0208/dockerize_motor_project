<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class failedJob extends Model
{
    protected $table = 'failed_jobs';
    use HasFactory;
}
