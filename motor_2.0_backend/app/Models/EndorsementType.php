<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EndorsementType extends Model
{
    use HasFactory;

    protected $table = 'endorsement_type';
    protected $primaryKey = 'endorsement_type_id';
    protected $guarded = [];
    public $timestamps = false;
}
