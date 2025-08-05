<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerPaymentLink extends Model
{
    use HasFactory;

    protected $table = 'customer_payment_link';
    protected $primaryKey = 'payment_link_id';
    protected $guarded = [];
    public $timestamps = false;
}
