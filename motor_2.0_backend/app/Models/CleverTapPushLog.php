<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CleverTapPushLog extends Model
{
     use HasFactory;
    protected $table = 'clever_tap_push_logs';
    protected $casts = [
        'payload' => 'array',
    ];
}