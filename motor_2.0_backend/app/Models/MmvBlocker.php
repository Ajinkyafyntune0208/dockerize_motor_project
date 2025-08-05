<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\ActivityTrait;


class MmvBlocker extends Model
{
    use HasFactory,ActivityTrait;
    protected $table = 'mmv_blocker';
    protected $fillable = [
        'product_sub_type_id',
        'seller_type',
        'segment',
        'manufacturer',
        'status'
    ];

    protected static function boot()
    {
        $serviceType = 'MMV_SELECTOR';
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
}
