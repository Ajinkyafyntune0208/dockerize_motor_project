<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class S3LogTablesModel extends Model
{
    use HasFactory,SoftDeletes;
    protected $table = 's3_log_tables';
}
