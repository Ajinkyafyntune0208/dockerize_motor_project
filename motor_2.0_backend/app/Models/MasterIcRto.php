<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MasterIcRto extends Model
{
    use HasFactory;

    protected $table = 'master_ic_rto';
    protected $primaryKey = 'ic_rto_id';
    protected $guarded = [];
    public $timestamps = false;
}
