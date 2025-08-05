<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SaleMast extends Model
{
    use HasFactory;

    protected $table = 'sale_mast';
    protected $guarded = [];
    public $timestamps = false;
}
