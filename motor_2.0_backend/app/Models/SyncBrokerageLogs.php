<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SyncBrokerageLogs extends Model
{
    use HasFactory;


    protected $guarded = [];
    protected $casts = [
        'old_config' => 'array',
        'new_config' => 'array'
    ];
}
