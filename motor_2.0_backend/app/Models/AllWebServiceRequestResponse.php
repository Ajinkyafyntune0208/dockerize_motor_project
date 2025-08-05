<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AllWebServiceRequestResponse extends Model
{
    use HasFactory;

    protected $table = 'all_web_service_request_response';
    protected $primaryKey = 'request_response_id';
    protected $guarded = [];
    public $timestamps = false;
}
