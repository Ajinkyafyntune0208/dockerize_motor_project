<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EndorsementRemarks extends Model
{
    use HasFactory;

    protected $table = 'endorsement_remarks';
    protected $primaryKey = 'endorsement_remark_id';
    protected $guarded = [];
    public $timestamps = false;
}
