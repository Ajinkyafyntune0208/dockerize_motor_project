<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IciciRtoMaster extends Model
{
    use HasFactory;

    protected $table = 'icici_rto_master';
    protected $primaryKey = 'icici_rto_master_id';
    protected $guarded = [];
    public $timestamps = false;
}
