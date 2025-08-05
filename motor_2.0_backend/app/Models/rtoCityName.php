<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class rtoCityName extends Model
{
    use HasFactory;
    protected $table = 'rto_city_names';

    protected $fillable = [
        'rto_city_name','rto_id'
    ];
}
