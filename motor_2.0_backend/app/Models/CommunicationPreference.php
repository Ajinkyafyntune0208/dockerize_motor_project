<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommunicationPreference extends Model
{
    use HasFactory;
    public $table = "communication_preference";

    protected $fillable = [
        'mobile' ,
        'email' ,
        'on_call' ,
        'on_sms',
        'on_email',
        'on_whatsapp',
    ];
}
