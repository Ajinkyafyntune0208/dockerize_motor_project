<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommunicationLogs extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_product_journey_id',
        'service_type',
        'request',
        'response',
        'communication_module',
        'status',
        'days',
        'old_user_product_journey_id',
        'prev_policy_end_end',
    ];
    
    protected $table = 'communication_logs';
    protected $primaryKey = 'id';
}
