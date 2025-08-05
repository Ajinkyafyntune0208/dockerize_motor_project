<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductTypeIcMap extends Model
{
    use HasFactory;

    protected $table = 'product_type_ic_map';
    protected $primaryKey = 'product_type_ic_map_id';
    protected $guarded = [];
    public $timestamps = false;
}
