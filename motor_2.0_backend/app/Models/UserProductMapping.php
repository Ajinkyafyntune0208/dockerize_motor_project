<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserProductMapping extends Model
{
    use HasFactory;

    protected $table = 'user_product_mapping';
    protected $primaryKey = 'user_map_id';
    protected $guarded = [];
    public $timestamps = false;
}
