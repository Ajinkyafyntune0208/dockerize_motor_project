<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MasterRto extends Model
{
    use HasFactory;

    protected $table = 'master_rto';
    protected $primaryKey = 'rto_id';
    protected $guarded = [];
    public $timestamps = false;
}
