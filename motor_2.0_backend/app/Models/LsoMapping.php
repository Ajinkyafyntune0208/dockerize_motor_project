<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LsoMapping extends Model
{
    use HasFactory;

    protected $table = 'lso_mapping';
    protected $primaryKey = 'lso_mapping_id';
    protected $guarded = [];
    public $timestamps = false;
}
