<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RenewalUpdationLog extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function getNewDataAttribute($value){
        return json_decode($value, true);
    }

    public function getOldDataAttribute($value){
        return json_decode($value, true);
    }

}
