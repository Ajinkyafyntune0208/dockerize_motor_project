<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EndorsementStatusMaster extends Model
{
    use HasFactory;

    protected $table = 'endorsement_status_master';
    protected $primaryKey = 'endorsement_status_id';
    protected $guarded = [];
    public $timestamps = false;
}
