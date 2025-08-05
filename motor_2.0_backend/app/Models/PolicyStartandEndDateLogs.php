<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PolicyStartandEndDateLogs extends Model
{
    use HasFactory;
    protected $table = 'policy_start_and_end_date_logs';
    protected $fillable = [
        'enquiry_id',
        'ic_id',
        'ic_name',
        'segment',
        'policy_type',
        'proceed',
        'status',
        'comments',
        'policy_start_date',
        'policy_end_date',
        'old_data'
    ];

    protected $casts = [
        'old_data' => 'array'
    ];
}
