<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MasterPincodeStateCity extends Model
{
    use HasFactory;

    protected $table = 'master_pincode_state_city';
    protected $primaryKey = 'master_pincode_id';
    protected $guarded = [];
    public $timestamps = false;
}
