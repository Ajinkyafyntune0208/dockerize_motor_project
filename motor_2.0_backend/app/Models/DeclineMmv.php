<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeclineMmv extends Model
{
    use HasFactory;

    protected $table = 'decline_mmv';
    protected $primaryKey = 'decline_mmv_id';
    protected $guarded = [];
    public $timestamps = false;
}
