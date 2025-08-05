<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductRtoZone extends Model
{
    use HasFactory;

    protected $table = 'product_rto_zone';
    protected $guarded = [];
    public $timestamps = false;
}
