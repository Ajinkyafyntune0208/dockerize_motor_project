<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommissionApiLog extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'request' => 'array',
        'response' => 'array',
    ];
}
