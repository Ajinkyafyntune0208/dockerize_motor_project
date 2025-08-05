<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GramcoverPostDataApi extends Model
{
    use HasFactory;

    protected $fillable = ['user_product_journey_id', 'token', 'request', 'response', 'status'];

    protected $casts = [
        'request' => 'array',
        'response' => 'array',
    ];
}
