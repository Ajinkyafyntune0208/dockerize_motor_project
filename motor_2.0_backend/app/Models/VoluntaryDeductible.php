<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VoluntaryDeductible extends Model
{
    use HasFactory;

    protected $table = 'voluntary_deductible';
    protected $primaryKey = 'voluntary_id';
    protected $guarded = [];
    public $timestamps = false;
}
