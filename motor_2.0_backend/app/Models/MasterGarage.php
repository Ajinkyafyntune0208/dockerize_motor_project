<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MasterGarage extends Model
{
    use HasFactory;

    protected $table = 'master_garage';
    protected $primaryKey = 'garage_id';
    protected $guarded = [];
    public $timestamps = false;
}
