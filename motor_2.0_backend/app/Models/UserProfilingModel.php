<?php

namespace App\Models;

use PhpParser\Node\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class UserProfilingModel extends Model
{
    use HasFactory;
    protected $table = 'user_profiling';
    protected $fillable = ['user_product_journey_id','request'];
    protected $casts = [
        'request' => 'array'
    ]; 
}
