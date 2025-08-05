<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\ActivityTrait;

class VahanServicePriorityList extends Model
{
    use HasFactory,ActivityTrait;

    protected $table = 'vahan_service_priority_list';
    protected $guarded = [];
    public $timestamps = false;

    protected static function boot()
    {
        $serviceType = 'VAHAN SERVICE CONFIGURATION';
        parent::boot();
        static::created(function ($model) use($serviceType){
            $model->logActivity('CREATED', $serviceType , $model->toArray());
        });

        static::updated(function ($model) use($serviceType){
            $oldData = $model->getOriginal();
            $newData = $model->getAttributes();
            $model->logUpdateActivity('UPDATED',$oldData, $newData, $serviceType);
            //Cache::forget(request()->header('host') . '_common_configurations_all');
        });

        static::deleted(function ($model) use($serviceType){
            $oldData = $model->getOriginal();
            $model->logDeletedActivity('DELETED', $serviceType ,$oldData);
        });
    }
}
