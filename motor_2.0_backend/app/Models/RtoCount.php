<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RtoCount extends Model
{
    use HasFactory;
    protected $table = 'rto_counts';

    protected $fillable = [
        'rto_code','policy_count','start_date'
    ];
}
