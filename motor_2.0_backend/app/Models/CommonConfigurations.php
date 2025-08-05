<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\ActivityTrait;
use Illuminate\Support\Facades\Cache;

class CommonConfigurations extends Model
{
    use HasFactory, ActivityTrait;
    protected $table = 'common_configurations';
    protected $guarded = [];
    protected static function boot()
    {
        parent::boot();
        // This code will be executed when the model is booted
        static::created(function ($model) {
            // Log the creation activity
            $model->logActivity('CREATED', 'COMMON CONFIGURATION', $model->toArray());
        });

        static::updated(function ($model) {
            // Get the original and new data
            $oldData = $model->getOriginal();
            $newData = $model->getAttributes();

            // Log the update activity
            $model->logUpdateActivity('UPDATED',$oldData, $newData, 'COMMON CONFIGURATION');
            Cache::forget(request()->header('host') . '_common_configurations_all');
            // Call function to set common configurations in cache
            setCommonConfigInCache();
        });
    }
}

