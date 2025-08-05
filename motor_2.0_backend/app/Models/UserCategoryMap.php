<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserCategoryMap extends Model
{
    use HasFactory;

    protected $table = 'user_category_map';
    protected $primaryKey = 'user_category_map_id';
    protected $guarded = [];
    public $timestamps = false;
}
