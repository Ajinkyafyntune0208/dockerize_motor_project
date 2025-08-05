<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\ActivityTrait;

class ConfigSetting extends Model
{
    use HasFactory,ActivityTrait;
    protected $guarded = [];

    protected static function boot()
    {
        $serviceType = 'CONFIG SETTINGS';
        parent::boot();
        static::created(function ($model) use($serviceType){
            $model->configCacheClear();
            $model->logActivity('CREATED',$serviceType , $model->toArray());
        });

        static::updated(function ($model) use($serviceType){
            $model->configCacheClear();
            $oldData = $model->getOriginal();
            $newData = $model->getAttributes();
            $model->logUpdateActivity('UPDATED',$oldData, $newData, $serviceType);
            //Cache::forget(request()->header('host') . '_common_configurations_all');
        });

        static::deleted(function ($model) use($serviceType){
            $model->configCacheClear();
            $oldData = $model->getOriginal();
            $model->logDeletedActivity('DELETED',$serviceType,$oldData);
        });
    }
}
