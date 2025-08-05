<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AbiblDailerApiLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_product_journey_id',
        'request',
        'response',
    ];

    protected $casts = [
        'request' => 'array',
        'response' => 'array',
    ];
}
