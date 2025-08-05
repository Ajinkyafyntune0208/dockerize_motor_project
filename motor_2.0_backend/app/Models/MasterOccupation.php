<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MasterOccupation extends Model
{
    use HasFactory;

    protected $table = 'master_occupation';
    protected $primaryKey = 'occupation_id';
    protected $guarded = [];
    public $timestamps = false;
}
