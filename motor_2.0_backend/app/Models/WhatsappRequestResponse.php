<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WhatsappRequestResponse extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'request' => 'array',
        'response' => 'array',
    ];

    public function status_data()
    {
        return $this->hasMany(self::class, 'request_id', 'request_id')->whereNull('response');
    }
}
