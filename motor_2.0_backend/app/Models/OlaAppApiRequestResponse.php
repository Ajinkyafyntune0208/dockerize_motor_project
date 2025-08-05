<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OlaAppApiRequestResponse extends Model
{
    use HasFactory;

    protected $table = 'ola_app_api_request_response';
    protected $primaryKey = 'web_service_request_response_id';
    protected $guarded = [];
    public $timestamps = false;
}
