<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PolicyReportData extends Model
{
    use HasFactory;
    protected $guarded = [];
    protected $casts = [
        'request' => 'array',
        'user_details' => 'array',
    ];
}
