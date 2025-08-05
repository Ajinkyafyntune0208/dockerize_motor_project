<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OnePayTransactionLog extends Model
{
    use HasFactory;
    protected $fillables = [
        'enquiry_id',
    ];

}
