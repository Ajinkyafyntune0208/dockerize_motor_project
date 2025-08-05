<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LogRotationModel extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'log_rotation';
    protected $primaryKey = 'log_rotation_id';
}
