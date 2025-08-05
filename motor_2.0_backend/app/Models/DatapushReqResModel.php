<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DatapushReqResModel extends Model
{
    use HasFactory;
    public $table = "datapush_req_res";

    protected $fillable = [
        "enquiry_id",
        "url",
        "request_headers",
        "dataenc",
        "datadenc",
        "status",
        "status_code",
        "request",
        "response",
    ];

    protected $casts = [
        "request_headers" => "array",
        "dataenc" => "array",
        "datadenc" => "array",
        "request" => "array",
        "response" => "array",
    ];
}
