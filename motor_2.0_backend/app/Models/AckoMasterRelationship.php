<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AckoMasterRelationship extends Model
{
    use HasFactory;

    protected $table = 'acko_master_relationship';
    protected $guarded = [];
    public $timestamps = false;
}
