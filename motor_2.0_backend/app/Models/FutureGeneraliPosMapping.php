<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FutureGeneraliPosMapping extends Model
{
    use HasFactory;
    protected $primaryKey = 'ic_mapping_id';
    protected $guarded = [];
    public $timestamps = false;
}
