<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmailSmsTemplate extends Model
{
    use HasFactory;

    protected $fillable = [ 'email_sms_name', 'type', 'subject', 'body', 'variable', 'status' ];
    
    protected $casts = [
        'variable' => 'array',
    ];
}
