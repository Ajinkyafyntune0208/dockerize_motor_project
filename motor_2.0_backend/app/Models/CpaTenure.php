<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CpaTenure extends Model
{
    use HasFactory;
    protected $table = 'cpa_tenure';
    protected $guarded = [];
    public $timestamps = false;
}
