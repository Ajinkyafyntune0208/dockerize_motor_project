<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EndorsementDetails extends Model
{
    use HasFactory;

    protected $table = 'endorsement_details';
    protected $primaryKey = 'endorsement_id';
    protected $guarded = [];
    public $timestamps = false;
}
