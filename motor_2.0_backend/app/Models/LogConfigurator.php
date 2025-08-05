<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LogConfigurator extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $table = 'log_configurator';
    protected $primaryKey = 'log_configurator_id';
    protected $guarded = [];
}
