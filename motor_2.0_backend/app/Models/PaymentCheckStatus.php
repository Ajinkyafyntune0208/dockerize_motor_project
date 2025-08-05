<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentCheckStatus extends Model
{
    use HasFactory;

    protected $table = 'payment_check_status';
    protected $primaryKey = 'user_product_journey_id';
    protected $guarded = [];
    public $timestamps = false;
}
