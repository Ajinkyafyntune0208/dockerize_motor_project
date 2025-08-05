<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LogRotationTables extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $table = 'log_rotation_tables';
    protected $primaryKey = 'log_rotation_id';
    public $timestamps = false;
    protected $guarded = [];

}
