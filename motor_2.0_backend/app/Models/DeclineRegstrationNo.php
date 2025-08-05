<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeclineRegstrationNo extends Model
{
    use HasFactory;

    protected $table = 'decline_regstration_no';
    protected $primaryKey = 'vehicle_id';
    protected $guarded = [];
    public $timestamps = false;
}
