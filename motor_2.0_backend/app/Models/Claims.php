<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Claims extends Model
{
    use HasFactory;

    protected $table = 'claims';
    protected $guarded = [];
    public $timestamps = false;
}
