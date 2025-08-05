<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AgentUpdationLog extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'old_data' => 'array',
        'new_data' => 'array',
        'request_payload' => 'array',
    ];
}
