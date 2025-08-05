<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmbeddedScrubData extends Model
{
    use HasFactory;

    protected $casts = [
        'request' => 'array',
        'response' => 'array'
    ];

    protected $guarded = [];
}
