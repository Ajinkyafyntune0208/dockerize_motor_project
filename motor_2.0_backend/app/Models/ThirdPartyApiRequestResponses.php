<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ThirdPartyApiRequestResponses extends Model
{
    use HasFactory;
    protected $table = 'third_party_api_request_responses';
    protected $primaryKey = 'id';
    protected $guarded = [];
}
