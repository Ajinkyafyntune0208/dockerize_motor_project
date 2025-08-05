<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RenewalMigrationAttemptLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'renewal_data_migration_status_id',
        'attempt',
        'type',
        'status',
        'extras'
    ];
}
