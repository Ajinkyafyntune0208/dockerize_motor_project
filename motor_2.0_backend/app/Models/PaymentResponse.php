<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentResponse extends Model
{
    use HasFactory;

    protected $table = 'payment_response';
    protected $primaryKey = 'id';
    protected $fillable = [
        'company_alias',
        'section',
        'response'
    ];
    public $timestamps = true;
}
