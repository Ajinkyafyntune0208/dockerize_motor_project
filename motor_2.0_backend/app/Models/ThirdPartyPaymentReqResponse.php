<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ThirdPartyPaymentReqResponse extends Model
{
    use HasFactory;
    protected $table = 'third_party_payment_req_response';
    protected $fillable = [
        'enquiry_id',
        'request',
        'response',
    ];
}
