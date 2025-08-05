<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MmvMissingExshowroom extends Model
{
    use HasFactory;

    protected $table = 'mmv_missing_exshowroom';
    protected $guarded = [];
    public $timestamps = false;
}
