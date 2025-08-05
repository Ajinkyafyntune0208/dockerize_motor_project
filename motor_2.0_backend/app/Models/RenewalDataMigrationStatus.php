<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RenewalDataMigrationStatus extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'policy_number',
        'registration_number',
        'user_product_journey_id',
        'request',
        'attempts',
        'status',
        'action'
    ];

    public function updation_log()
    {
        return $this->hasMany(RenewalUpdationLog::class, 'renewal_data_migration_status_id');
    }

    public function migration_attempt_logs()
    {
        return $this->hasMany(RenewalMigrationAttemptLog::class, 'renewal_data_migration_status_id');
    }

    public function wealth_maker_api_log()
    {
        return $this->hasOne(WealthMakerApiLogs::class, 'renewal_data_migration_status_id');
    }

    public function user_product_journey()
    {
        return $this->hasOne(UserProductJourney::class, 'user_product_journey_id', 'user_product_journey_id');
    }
}
