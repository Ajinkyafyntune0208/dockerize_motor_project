<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserTrail extends Model
{
     protected $table = 'user_trails';

     protected $fillable = [
        'user_id',
        'session_id',
        'url',
        'method',
        'parameters',
    ];
 
     protected $primaryKey = 'id';
     public $timestamps = true; 
}