<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuthorizationRequest extends Model
{
    use HasFactory;

    protected $table = 'authorization_request';
    protected $primaryKey = 'authorization_request_id';
    protected $guarded = [];
}
