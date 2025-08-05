<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServerToServerModel extends Model
{
    use HasFactory;

    protected $table = 'server_to_server_req_response';
    protected $primaryKey = 'id';
    protected $guarded = [];
}
