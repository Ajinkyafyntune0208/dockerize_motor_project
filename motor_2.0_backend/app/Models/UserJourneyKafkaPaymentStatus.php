<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserJourneyKafkaPaymentStatus extends Model
{
    use HasFactory;
    protected $table = 'user_journey_kafka_payment_status';
    protected $primaryKey = 'id';
    protected $guarded = [];
    public $timestamps = true;
}
