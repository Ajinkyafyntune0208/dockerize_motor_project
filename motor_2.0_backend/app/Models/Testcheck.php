<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Testcheck extends Model
{
    use HasFactory;

    protected $table = 'testcheck';
    protected $guarded = [];
    public $timestamps = false;
}
