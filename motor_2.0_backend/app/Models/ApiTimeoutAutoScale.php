<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApiTimeoutAutoScale extends Model
{
    use HasFactory;

    protected $fillable = [
        'endpoint_url',
        'unique_record',
        'company_alias',
        'transaction_type',
        'timeout',
    ];
}
