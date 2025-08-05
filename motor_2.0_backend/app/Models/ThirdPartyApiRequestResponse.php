<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ThirdPartyApiRequestResponse extends Model
{
    use HasFactory; 

    protected $fillable = [ "name", "url", "request", "response", "headers", "response_headers", "options" , "response_time", "http_status"];

     protected $casts = [
         "request" => "array",
         "response" => "array",
         "headers" => "array",
         "response_headers" => "array",
         "options" => "array",
     ];
}
