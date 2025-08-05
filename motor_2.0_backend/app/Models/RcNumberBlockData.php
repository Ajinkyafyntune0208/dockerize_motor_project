<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RcNumberBlockData extends Model
{
    use HasFactory;

    protected $fillable = ['rc_number','resources','status'];
}
