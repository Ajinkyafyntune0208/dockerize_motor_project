<?php

namespace App\Models;

use App\Traits\ActivityTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PremCalcBucket extends Model
{
    use HasFactory, ActivityTrait;

    protected $guarded = [];

    protected static function boot()
    {
        $serviceType = 'PREM_CALC_BUCKET';
        parent::boot();
        static::created(function ($model) use($serviceType){
            $model->logActivity('CREATED',$serviceType , $model);
        });
        static::updated(function ($model) use($serviceType){
            $oldData = $model->getOriginal();
            $newData = $model->getAttributes();
            $model->logUpdateActivity('UPDATED',$oldData, $newData, $serviceType);

        });

        static::deleted(function ($model) use($serviceType){
            $oldData = $model->getOriginal();
            $model->logDeletedActivity('DELETED',$serviceType,$oldData);
        });

    }

    public  function lists()
    {
        return $this->hasMany(PremCalcBucketList::class);
    }
}
