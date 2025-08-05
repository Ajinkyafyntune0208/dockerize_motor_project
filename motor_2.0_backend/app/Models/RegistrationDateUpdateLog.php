<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\ActivityTrait;


class RegistrationDateUpdateLog extends Model
{
    use HasFactory, ActivityTrait;


    protected $table = 'registration_date_update_logs';
    protected $guarded = [];

    protected $casts = [
        'old_date' => 'array',
        'new_date' => 'array',
    ];

    protected static function boot()
    {

        $serviceType = 'Registration Date Update';
        parent::boot();
        static::created(function ($model) use ($serviceType) {
            $model->logActivity('CREATED', $serviceType, $model);
        });
    }
}
