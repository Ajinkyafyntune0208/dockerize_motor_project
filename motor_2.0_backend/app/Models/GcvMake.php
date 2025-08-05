<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GcvMake extends Model
{
    use HasFactory;

    protected $table = 'gcv_make';
    protected $primaryKey = 'make_id';
    protected $guarded = [];
    public $timestamps = false;
}
