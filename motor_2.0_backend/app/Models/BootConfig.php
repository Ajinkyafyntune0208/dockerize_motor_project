<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\ActivityTrait;
use Illuminate\Support\Facades\Cache;

class BootConfig extends Model
{
    use HasFactory,ActivityTrait;
    protected $table    = 'config_boots';
    protected $fillable = ['key', 'value'];

    
    public static function getValue ($key)
    {
        return self::where('key', $key)->value('value') ?? env($key); 
    }

    protected static function boot()
    {
        parent::boot();

        static::created(function ($model) {
            // Log the creation activity
            $model->logActivity('CREATED', 'CONFIG BOOT', $model->toArray());
        });

        static::updated(function ($model) {

            $oldData = $model->getOriginal();
            $newData = $model->getAttributes();

            $model->logUpdateActivity('UPDATED',$oldData, $newData, 'CONFIG BOOT');
            Cache::forget(request()->header('host') . '_config_boot_all');
            setCommonConfigInCache();
        });
    }
}
