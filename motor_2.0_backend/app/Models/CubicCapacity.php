<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CubicCapacity extends Model
{
    use HasFactory;

    protected $table = 'cubic_capacity';
    protected $primaryKey = 'cub_id';
    protected $guarded = [];
    public $timestamps = false;
}
