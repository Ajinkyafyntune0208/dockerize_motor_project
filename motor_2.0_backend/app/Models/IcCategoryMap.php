<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IcCategoryMap extends Model
{
    use HasFactory;

    protected $table = 'ic_category_map';
    protected $primaryKey = 'ic_category_map_id';
    protected $guarded = [];
    public $timestamps = false;
}
